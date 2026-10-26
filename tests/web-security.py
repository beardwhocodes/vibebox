#!/usr/bin/env python3
"""Exercise the generated nginx configuration with real PHP-FPM and synthetic data."""
import json
import os
from pathlib import Path
import re
import shutil
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
NGINX_IMAGE = os.environ.get("TEST_NGINX_IMAGE", "nginx:stable-alpine")
PHP_IMAGE = os.environ.get("TEST_PHP_IMAGE", "php:8.5-fpm-alpine")
UBUNTU_IMAGE = os.environ.get("TEST_UBUNTU_IMAGE", "ubuntu:24.04")


def command(*args):
    return subprocess.check_output(args, text=True, stderr=subprocess.STDOUT).strip()


def request(base, path):
    try:
        response = urllib.request.urlopen(base + path, timeout=5)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read()


def main():
    source = (ROOT / "vibebox.sh").read_text()
    match = re.search(r"cat > /etc/nginx/sites-available/vibebox <<EOF\n(.*?)\nEOF", source, re.S)
    assert match, "nginx configuration not found"
    for image in [NGINX_IMAGE, PHP_IMAGE, UBUNTU_IMAGE]:
        try:
            command("docker", "image", "inspect", image)
        except subprocess.CalledProcessError:
            command("docker", "pull", image)
    assert "sqlite3" in command("docker", "run", "--rm", PHP_IMAGE, "php", "-m").splitlines()

    containers = []
    with tempfile.TemporaryDirectory(prefix="vibebox-web-test-") as temporary:
        fixture = Path(temporary)
        fixture.chmod(0o755)
        app, data = fixture / "app", fixture / "data"
        app.mkdir(mode=0o755)
        data.mkdir(mode=0o777)
        data.chmod(0o777)  # Synthetic files only; the unprivileged container writes here.
        functions = fixture / "installer-functions.sh"
        functions.write_text(source.split("# Orchestration\n", 1)[0]
                             + '\napt-get() { return 99; }\napp_starter\n')
        (fixture / "data-link").symlink_to("app", target_is_directory=True)
        guards = [
            ("/fixture/app", "/fixture/app/private", "APP_DATA_DIR must be outside APP_DIR"),
            ("/fixture/app", "/fixture/data-link", "APP_DATA_DIR must be outside APP_DIR"),
            ("/", "/fixture/data", "must not be the filesystem root"),
            ("/fixture/app", "/", "must not be the filesystem root"),
            ("/fixture/app", "relative/path", "App paths must be absolute"),
        ]
        (app / "app.db").write_text("SYNTHETIC_PRIVATE_FIXTURE")
        guards.append(("/fixture/app", "/fixture/data", "Legacy app.db found"))
        for public_path, private_path, error in guards:
            result = subprocess.run(
                ["docker", "run", "--rm", "--network", "none", "--read-only", "--cap-drop", "ALL",
                 "--user", "65534:65534", "-v", str(fixture) + ":/fixture:ro",
                 "-e", "APP_DIR=" + public_path, "-e", "APP_DATA_DIR=" + private_path,
                 "-e", "INSTALL_APP=true", UBUNTU_IMAGE, "bash", "/fixture/installer-functions.sh"],
                capture_output=True, text=True,
            )
            assert result.returncode != 0 and error in result.stderr, result.stderr
        blocked = [
            "/app.db", "/app.db-wal", "/app.db-shm", "/app.db-journal",
            "/APP.DB", "/nested/data.sqlite3", "/nested/data.sqlite-wal",
            "/backup.sql.gz", "/index.php.bak", "/index.php~", "/config.yml",
            "/.env", "/.env.production", "/.git/config", "/nested/.env",
            "/.git/secret.php", "/app.db.php",
        ]
        for path in blocked:
            file = app / path.lstrip("/")
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text("SYNTHETIC_PRIVATE_FIXTURE")
        (app / "assets").mkdir()
        (app / "assets/style.css").write_text("body { color: blue; }")
        (app / "assets/app.js").write_text("console.log('public');")

        # Render the installer's heredoc with bash, including its real escaping.
        rendered = subprocess.check_output(
            ["bash", "-c", "cat <<EOF\n" + match[1] + "\nEOF\n"],
            env={**os.environ, "APP_DIR": "/fixture/app", "APP_DATA_DIR": "/fixture/data",
                 "APP_DOMAIN": "localhost", "php_sock": "/tmp/unused.sock"}, text=True,
        )
        rendered = rendered.replace("listen 127.0.0.1:80;", "listen 0.0.0.0:8080;")
        rendered = rendered.replace("include snippets/fastcgi-php.conf;", "include /fixture/fastcgi.conf;")
        rendered = rendered.replace("fastcgi_pass unix:/tmp/unused.sock;", "fastcgi_pass 127.0.0.1:9000;")
        (fixture / "fastcgi.conf").write_text(
            "include /etc/nginx/fastcgi_params;\n"
            "fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n"
            "try_files $fastcgi_script_name =404;\n"
        )
        (fixture / "nginx.conf").write_text(
            "pid /tmp/nginx.pid; error_log stderr; events {} http { access_log off;\n"
            "client_body_temp_path /tmp/client; fastcgi_temp_path /tmp/fastcgi;\n"
            "proxy_temp_path /tmp/proxy; uwsgi_temp_path /tmp/uwsgi; scgi_temp_path /tmp/scgi;\n"
            + rendered + "\n}\n"
        )
        (fixture / "fpm.conf").write_text(
            "[global]\nerror_log = /proc/self/fd/2\ndaemonize = no\n"
            "[www]\nlisten = 127.0.0.1:9000\npm = static\npm.max_children = 2\n"
            "catch_workers_output = yes\n"
        )
        for path in fixture.rglob("*"):
            path.chmod(0o755 if path.is_dir() else 0o644)
        data.chmod(0o777)

        common = ["--user", "82:82", "--cap-drop", "ALL", "--security-opt", "no-new-privileges",
                  "--read-only", "--tmpfs", "/tmp:rw,noexec,nosuid,size=16m",
                  "-v", str(fixture) + ":/fixture:ro"]
        try:
            php = command("docker", "run", "-d", "--name", "vibebox-php-" + uuid.uuid4().hex[:12],
                          *common, "-v", str(data) + ":/fixture/data:rw", "-p", "127.0.0.1::8080",
                          PHP_IMAGE, "php-fpm", "-F", "-y", "/fixture/fpm.conf", "-d", "opcache.enable=0")
            containers.append(php)
            nginx = command("docker", "run", "-d", *common, "--network", "container:" + php,
                            "--entrypoint", "nginx", NGINX_IMAGE, "-c", "/fixture/nginx.conf", "-g", "daemon off;")
            containers.append(nginx)
            address = command("docker", "port", php, "8080/tcp")
            base = "http://" + address
            shutil.copyfile(ROOT / "starter/index.php", app / "index.php")
            for attempt in range(50):
                try:
                    status, _ = request(base, "/?r=health")
                    if status == 200:
                        break
                except urllib.error.URLError:
                    pass
                time.sleep(.1)
            else:
                raise AssertionError("PHP-FPM did not become ready")

            for path in blocked + ["/%2eenv", "/nested/%2eenv", "/app.db?download=1"]:
                status, body = request(base, path)
                assert status == 404 and b"SYNTHETIC_PRIVATE_FIXTURE" not in body, (path, status)
            for path in ["/assets/style.css", "/assets/app.js"]:
                status, body = request(base, path)
                assert status == 200 and body == (app / path.lstrip("/")).read_bytes(), path

            fallback = re.search(r"cat > \"\$APP_DIR/index.php\" <<'PHP'\n(.*?)\nPHP", source, re.S)
            assert fallback
            for label, script in [("starter", (ROOT / "starter/index.php").read_text()),
                                  ("fallback", fallback[1]), ("demo", (ROOT / "demo/index.php").read_text())]:
                (app / "index.php").write_text(script)
                connection = sqlite3.connect(data / "app.db")
                before = connection.execute("SELECT COUNT(*) FROM visits").fetchone()[0]
                connection.close()
                status, body = request(base, "/")
                assert status == 200 and b"<!doctype html>" in body, (label, status)
                connection = sqlite3.connect(data / "app.db")
                assert connection.execute("SELECT COUNT(*) FROM visits").fetchone()[0] == before + 1, label
                assert connection.execute("PRAGMA integrity_check").fetchone()[0] == "ok", label
                connection.close()
                assert (app / "app.db").read_text() == "SYNTHETIC_PRIVATE_FIXTURE", label
                if label != "fallback":
                    status, body = request(base, "/?r=health")
                    assert status == 200 and json.loads(body)["ok"] is True, label
            print(json.dumps({"private_paths_blocked": len(blocked) + 3, "public_assets": 2,
                              "installer_path_guards": len(guards),
                              "php_applications": ["starter", "fallback", "demo"],
                              "private_database_writes": "passed", "result": "passed"}))
        finally:
            for container in reversed(containers):
                command("docker", "rm", "-f", container)


if __name__ == "__main__":
    main()

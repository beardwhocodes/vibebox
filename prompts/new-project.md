# Prompt — scaffold a new PHP+SQLite project

Spins up a new app on this VPS using the vibe stack. Don't fight it with frameworks.

```
Scaffold a new PHP + SQLite + jQuery web app at /srv/<NAME> on this box, following the same
style as /srv/app (the vibe starter). Constraints:

- Vanilla PHP only. No framework, no Composer, no Node build step.
- One `index.php` switch-router monolith; add files only if it genuinely helps.
- SQLite in `<NAME>.db` with `PRAGMA journal_mode=WAL` and `PRAGMA synchronous=NORMAL`.
- Server-rendered HTML; vanilla JS or jQuery for any interactivity.
- Add a `CLAUDE.md` in the project root that copies the rules from /srv/app/CLAUDE.md and
  adds anything specific to this project.

Concrete tasks:
1. mkdir /srv/<NAME>, chown www-data:www-data, create index.php with a "/", "/health" (JSON),
   and one example feature route I describe below.
2. Initialize <NAME>.db with the WAL/synchronous PRAGMAs and at least one table for the example
   feature.
3. Add an nginx server block at /etc/nginx/sites-available/<NAME> listening on 127.0.0.1:80,
   server_name <DOMAIN> (only loopback — public access is via the Cloudflare tunnel). Symlink
   to sites-enabled, `nginx -t`, then `systemctl reload nginx`.
4. Tell me the exact public-hostname mapping to add in the Cloudflare Zero Trust dashboard
   (hostname -> http://localhost:80, set Host header to <DOMAIN>).
5. Append the new DB to /etc/litestream.yml so it gets backed up too. Don't restart litestream
   yet — just show me the diff.

Feature for the example route:
<DESCRIBE THE FEATURE IN 2-3 SENTENCES>

When done, run `curl -H "Host: <DOMAIN>" http://localhost/` and `curl -H "Host: <DOMAIN>" http://localhost/?r=health`
and show me both responses. Don't touch firewall or systemd services beyond what's listed.
```

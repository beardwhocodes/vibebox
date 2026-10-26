# Private files and web access

Vibebox v0.4 stores SQLite data outside the web root.
The default paths are `/srv/app` for public code and `/srv/data/vibebox/app.db` for private data.
Set `APP_DATA_DIR` in `config.env` to change the data directory.
The installer rejects a data directory inside `APP_DIR`.

nginx passes the database path to PHP through `VIBEBOX_DB_PATH`.
The starter, inline fallback, demo, and new Litestream configuration use this path.
nginx blocks dotfiles, database files, database journals, and common backup or configuration files with HTTP 404.
These rules run before PHP and static file handling.

Keep credentials, database files, uploads with private data, and backups outside the web root.
The filename rules provide extra protection. They do not identify every possible private file.
Review each new public directory and application route.

## Existing installations

Older versions put `app.db` inside `/srv/app`.
An older nginx configuration can serve this file to anyone who knows its URL.
The v0.4 installer refuses this legacy database before it changes application files.
It does not move existing data or rewrite existing application code.

Use a maintenance window for the database move.
Do not copy a live SQLite file with `cp`; committed data can remain in its WAL file.

1. Save the existing nginx configuration and application code outside the web root.
2. Add both private-file `location` rules from `vibebox.sh` before the PHP handler.
3. Run `nginx -t`.
4. Reload nginx. Wait until fresh requests return HTTP 404 for `/app.db` and `/.env`.
5. Identify all writers, including PHP-FPM, worker services, scheduled jobs, and Litestream.
6. Stop those writers. Keep the private-file rules active.
7. Create a database backup outside the web root with SQLite's backup API.
8. Run `PRAGMA integrity_check` on the backup. Continue only when it returns `ok`.
9. Record the database table counts.
10. Create `/srv/data/vibebox` with owner `www-data:www-data` and mode `0750`.
11. Run `PRAGMA wal_checkpoint(TRUNCATE)` against the source database. Require its first result value to be zero.
12. Move `app.db` and any remaining `app.db-wal`, `app.db-shm`, or `app.db-journal` files into the data directory.
13. Give the database files owner `www-data:www-data` and mode `0640`.
14. Update every database caller to use the private path.
15. Add `fastcgi_param VIBEBOX_DB_PATH /srv/data/vibebox/app.db;` inside the nginx PHP handler.
16. Update the database path in `/etc/litestream.yml` and any other backup jobs.
17. Check PHP syntax, nginx syntax, database integrity, and the recorded table counts.
18. Restart the writers and reload nginx.
19. Check the normal page and health route. Confirm the database remains outside the web root.
20. Confirm public requests for private files return HTTP 404. Use HEAD requests for real private files.

If migration fails, stop the writers before rollback.
Restore the saved paths and code together. Keep the private-file nginx rules active.
Keep the verified backup until the application and remote restore checks pass.
Purge any cached private file from your proxy or CDN after the fix.
Review the exposed data and rotate credentials if that data contained credentials.

## Agent permissions

Use `claude` with its normal permission checks.
New images do not install the old root `c` helper.
The `claude_code` phase removes the known helper from older installations.
Existing shells can retain that function until they exit. Run `unset -f c` in those shells.

Permission bypass requires an explicit command:

```bash
IS_SANDBOX=1 claude --dangerously-skip-permissions
```

This command grants the agent the current user's access without permission prompts.
`IS_SANDBOX=1` provides no operating system isolation.
Use bypass only on a disposable server with verified backups and restricted access.

## Regression checks

Run `python3 tests/web-security.py` from the published repository root.
The test needs Docker and pulls the official nginx, PHP-FPM, and Ubuntu images when absent.
It uses synthetic data, a random loopback port, and temporary containers.
It checks private-file denial, PHP routes, static assets, and database placement.

# CLAUDE.md — agent rules for this box

You are coding on a disposable, isolated VPS. Ship fast, keep it simple. This stack
is deliberately "dumb" so you make fewer mistakes — do not fight it.

## The stack (do not change without being asked)
- **PHP only**, vanilla. No framework (no Laravel/Symfony). No Composer unless asked.
- **Frontend: server-rendered HTML + a little vanilla JS / jQuery.** No React/Vue/build step.
- **SQLite** for all data (`/srv/data/vibebox/app.db`, WAL mode). nginx supplies overrides through `VIBEBOX_DB_PATH`.
- **No build tools, no bundler, no transpile.** Edits are live — the file IS the deploy.
- One growing `index.php` switch-router monolith is fine. Split into a few files only if it truly helps.

## How things run here
- nginx listens on `127.0.0.1:80`; the public internet reaches it only through the Cloudflare tunnel.
- SSH is Tailscale-only; the inbound firewall is fully closed. Do not open ports or change ufw/sshd
  without being explicitly asked — you could lock the owner out.
- There is no separate deploy step: editing files under the app dir updates the live site. Refresh the
  browser to see changes.

## Working style
- Make the smallest change that works, then verify it (hit the route, check `curl localhost`).
- Match the existing style of `index.php`. No new dependencies unless asked.
- Use SQLite indexes for any column you filter/sort on. Keep WAL mode on.
- Escape user input in HTML (`htmlspecialchars`) and use prepared statements for any user data in SQL.

## Safety
- Keep databases, credentials, private uploads, and backups outside the web root.
- Preserve database and WAL files. Use SQLite's backup API before a migration.
- Litestream requires a configured remote replica and a verified restore. Check both before relying on backups.
- Keep normal agent permission checks enabled. Permission bypass requires an explicit owner request.
- Never paste secrets into code; read them from env or `/etc/`-level config.
- If asked to do something destructive (drop tables, rm -rf, change firewall/ssh), state the risk first.

# Claude Prompt Pack

Battle-ready prompts for running Claude Code on a vibe-coding VPS. Each one is a self-contained
copy-paste — fill in `<…>` placeholders, then paste into `claude` (or `c` on disposable boxes).

| # | Prompt | Use when |
|---|---|---|
| 1 | [`security-audit.md`](./security-audit.md) | After `vibebox.sh` ran — verify the box matches the levelsio model |
| 2 | [`new-project.md`](./new-project.md) | Scaffolding a new PHP+SQLite app on the box |
| 3 | [`add-route.md`](./add-route.md) | Adding a route + handler to the starter monolith |
| 4 | [`sqlite-migration.md`](./sqlite-migration.md) | Schema change — **with backup first** |
| 5 | [`continue-from-claude-md.md`](./continue-from-claude-md.md) | "Autopilot until the todo list is empty" (franze pattern) |
| 6 | [`cloudflared-hostname.md`](./cloudflared-hostname.md) | Routing a new subdomain through the tunnel |
| 7 | [`litestream-r2.md`](./litestream-r2.md) | Pointing Litestream at your R2 bucket |
| 8 | [`telegram-bot-daemon.md`](./telegram-bot-daemon.md) | A persistent bot daemon (the hoodmaps pattern) |

## House rules these prompts follow
- **Backups before destructive ops.** Every schema/data prompt makes a copy first.
- **State the risk.** Prompts that touch firewall/ssh/cloudflared ask Claude to summarize what it
  will change before doing it.
- **Don't fight the stack.** PHP + SQLite + jQuery, no framework, no build step — see `starter/CLAUDE.md`.
- **No secrets in code.** Reads from env vars / `/etc/`-level config.

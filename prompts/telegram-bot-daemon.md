# Prompt — install a Telegram bot as a system daemon

The hoodmaps pattern: a persistent bot running next to your web app on the same VPS, restarted
by systemd, isolated to its own user.

```
Install a Telegram bot for <PROJECT> as a systemd service on this box. The bot's job:

<DESCRIBE THE BOT IN 2-3 SENTENCES — what it does, what triggers it, what it reads/writes>

Constraints:
- Match the vibe stack — PHP if it can run as a long-running poll loop, otherwise a small
  Python or Node script is fine. Pick the simplest tool for the job and tell me why.
- The bot token goes in /etc/<PROJECT>/bot.env (mode 0600, owned by the service user).
  NEVER hardcode it. NEVER commit it. The script reads from env.
- New non-root user `<PROJECT>-bot`, no shell. Service runs as that user.
- The bot can read/write /srv/<PROJECT>/<DB> via the same SQLite file the web app uses. WAL mode
  means concurrent reads + the single writer (whichever holds the write lock) is fine.

Deliverables:
1. The bot script at /srv/<PROJECT>/bot/main.<ext>.
2. /etc/systemd/system/<PROJECT>-bot.service with:
   - User=<PROJECT>-bot
   - EnvironmentFile=/etc/<PROJECT>/bot.env
   - Restart=on-failure, RestartSec=5
   - Hardening: NoNewPrivileges=true, ProtectSystem=strict, ProtectHome=true,
     ReadWritePaths=/srv/<PROJECT>, PrivateTmp=true
3. Enable + start it. Confirm with `systemctl is-active <PROJECT>-bot` and tail the logs
   (`journalctl -u <PROJECT>-bot -n 30`) — no crash loops.
4. Test by sending one message to the bot and showing me the corresponding log line.

Do not touch firewall, sshd, cloudflared, or nginx for any of this.
```

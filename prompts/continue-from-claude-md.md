# Prompt — "Continue, if done read CLAUDE.md and continue" (franze pattern)

A long-running autopilot. The HN user **franze** runs this on a Hetzner box: a watcher fires the
prompt every ~12 minutes, Claude works the todo list, and stops/notifies when done. Use this only
with explicit permission bypass, on a **disposable** box, with backups confirmed.

## The prompt (paste into Claude Code; loop externally)

```
Continue with whatever I was working on.

If you don't know what to continue:
1. Read CLAUDE.md in the current directory — it has the project rules and ongoing context.
2. Look at the most recent commits / file mtimes to figure out what's in flight.
3. Find the highest-priority unfinished item and work on it.

Working rules:
- Make the smallest change that moves the work forward, then verify it (run the test or curl
  the route).
- After each meaningful change, append a one-line journal entry to CLAUDE.md under a "## Journal"
  heading at the bottom: `YYYY-MM-DD HH:MM — <what changed, in one sentence>`. This is how the
  next invocation knows where to pick up.
- Never touch firewall, ssh, or cloudflared without first writing what you'd change and pausing
  for me to confirm.
- If a test/curl fails twice in a row on the same approach, STOP and write a note in CLAUDE.md
  describing the failure mode. Don't keep retrying the same broken thing.
- When CLAUDE.md says the todo list is empty, write a final journal entry "todo list empty —
  stopping" and exit instead of inventing new work.
```

## The loop (cron / systemd timer on the box)

```bash
# /etc/systemd/system/vibe-autopilot.service
[Service]
Type=oneshot
WorkingDirectory=/srv/<PROJECT>
ExecStart=/bin/bash -lc 'echo "continue, read CLAUDE.md if you need context" | IS_SANDBOX=1 claude --dangerously-skip-permissions'
User=root

# /etc/systemd/system/vibe-autopilot.timer
[Timer]
OnUnitActiveSec=12min
Persistent=true
[Install]
WantedBy=timers.target
```

```
systemctl enable --now vibe-autopilot.timer
journalctl -u vibe-autopilot -f       # watch it work
systemctl stop vibe-autopilot.timer   # kill switch
```

**Warnings.** This runs the agent unattended. Have Litestream running. Have Hetzner snapshots
running. Don't aim it at code that touches money or PII. If `CLAUDE.md` doesn't exist in the
project root yet, copy it from [`../starter/CLAUDE.md`](../starter/CLAUDE.md) first.

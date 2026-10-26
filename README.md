# vibebox — a secure agent-ready VPS in one command

Spin up the @levelsio "vibe coding on a server" stack without the tedious, error-prone
manual setup. The installer and required security setup prepare a fresh Ubuntu box for agent-driven development:

- 🔒 **Private administration** — Tailscale SSH, separate server identities, and closed provider ingress after the security setup.
- 🤖 **Claude Code installed on the box** — code from your laptop *or* your phone (Termius + Mosh + tmux)
- 🧱 **PHP + SQLite + nginx starter** — no framework, no build step (the stack agents make fewest mistakes on)
- 💾 **Litestream installed** for SQLite — configure a replica and prove a restore before relying on backups.
- 🪤 **Lockout protection** — a deadman timer auto-reverts the firewall if Tailscale isn't actually working

> **What you're buying: convenience.** Every step below is documented and free to do by hand — that's
> the point. Doing it by hand is fiddly and easy to get dangerously wrong (lock yourself out, leave a port
> open and get cryptomined, skip backups). This makes it ~5 minutes and hard to screw up.

---

## What it costs you (we don't mark any of this up)
| Service | Role | Typical cost |
|---|---|---|
| Hetzner / DigitalOcean | the VPS | $5–$99/mo |
| Cloudflare | tunnel + DNS + DDoS | free tier |
| Tailscale | private SSH | free tier |
| Anthropic | Claude Code | your existing plan / API |

---

## ⚡ Fastest path: let your agent do it (recommended)

Generate three auth tokens (Hetzner, Cloudflare, Tailscale — ~5 min total), paste a
prompt, and your AI coding agent runs the entire kit for you using Packer +
Terraform + the API. You never touch the Cloudflare dashboard, never copy a tunnel
token, never SSH manually. Total wall time: **~10 minutes**.

→ **[`QUICKSTART.md`](./QUICKSTART.md)** — the agent-driven path.

The agent reads [`CLAUDE.md`](./CLAUDE.md) at the repo root for its runbook
(execution plan, safety gates, known traps + fixes).

## Three other ways to use this

- **A — Just `vibebox.sh` on stock Ubuntu** (manual, ~5 min): create a fresh Ubuntu box, SSH in, run `vibebox.sh`. See "Run it" below.
- **B — Packer image + Terraform** (fastest first boot, ~40 sec): bake a Hetzner snapshot with all installs done, then spin boxes from it. See [`packer/`](./packer) and [`terraform/`](./terraform).
- **C — The full manual walkthrough**: [`GUIDE.md`](./GUIDE.md) explains every step in detail — the trap-free Cloudflare flow, mobile coding setup, 3-2-1 backups, recovery scenarios.

The agent-driven path uses **B under the hood** with API calls instead of CLI clicks. If you'd rather drive it yourself, A is the manual minimum.

## Prerequisites for the manual paths (~5 min one-time)
1. **A VPS.** Create a fresh **Ubuntu 26.04** (or 24.04) server on Hetzner or DigitalOcean. **Add your SSH public
   key during creation** (this auto-disables password login).
2. **Tailscale auth key.** [Tailscale admin](https://login.tailscale.com/admin/settings/keys) → generate a
   reusable or ephemeral key.
3. **Cloudflare tunnel token.** Cloudflare dashboard → **Zero Trust → Networks → Tunnels** → create a
   tunnel → copy the **token**. Under the tunnel's **Public Hostname**, map your domain → `http://localhost:80`.
   *(Optional — skip it and the box just stays fully closed until you add web later.)*

## Run it
```bash
ssh root@YOUR_SERVER_IP                 # the public IP, this one last time

# get the script + config
curl -fsSL https://raw.githubusercontent.com/beardwhocodes/vibebox/main/vibebox.sh -o vibebox.sh
curl -fsSL https://raw.githubusercontent.com/beardwhocodes/vibebox/main/config.env.example -o config.env
nano config.env                         # paste your Tailscale key + Cloudflare token + domain
less vibebox.sh                         # (recommended) read before you run

bash vibebox.sh
```

Then **verify you're not locked out** — from your laptop with Tailscale running:
```bash
ssh vibebox-1            # your TS_HOSTNAME (Tailscale MagicDNS), or ssh root@<tailscale-ip>
bash vibebox.sh confirm  # cancels the deadman so the firewall stays up
```
During bootstrap, the deadman can revert UFW after 10 minutes.
The initial provider rule and OS network configuration must still permit SSH.

## Finish the security setup

Complete [SECURITY-SETUP.md](./SECURITY-SETUP.md) before declaring the server ready.
The installer does not configure tailnet isolation or remove the provider SSH rule.
The guide covers server tags, administrator access, provider verification, backups, and recovery.

## Then code
```bash
ssh vibebox-1
cd /srv/app
claude          # safe mode (asks before edits)
```
Edit files, refresh your domain. No git push, no deploy.

---

## What each phase does
| Phase | Action |
|---|---|
| `preflight` | checks root + Ubuntu + connectivity, loads `config.env` |
| `base_packages` | ufw, fail2ban, unattended-upgrades, git, tmux, mosh |
| `ssh_harden` | key-only, no passwords (refuses if you have no key — anti-lockout) |
| `tailscale_up` | installs Tailscale, joins your tailnet, **verifies an IP before firewalling** |
| `firewall` | deny all inbound; SSH only from Tailscale; **arms the deadman** |
| `cloudflared_tunnel` | outbound Cloudflare tunnel → serve web with no open ports |
| `fail2ban_setup` | bans SSH brute-forcers |
| `auto_upgrades` | unattended security upgrades + 04:00 auto-reboot |
| `claude_code` | Node + Claude Code with normal permission checks |
| `app_starter` | nginx + PHP-FPM + SQLite + the starter (localhost-only) |
| `backups` | Litestream for SQLite (point it at your R2/S3 bucket) |

Re-run any single phase: `bash vibebox.sh firewall`. The script is idempotent.

## Recovery / footguns
- **Locked out of SSH?** The deadman applies only during initial host-firewall setup. It cannot repair the provider firewall or tailnet policy. After confirmation, use the [recovery steps](./SECURITY-SETUP.md#4-keep-a-usable-recovery-path).
- **Permission bypass requires an explicit command.** The old root `c` helper is removed. See [agent permissions](./WEB-SECURITY.md#agent-permissions).
- **Keep private data outside the web root.** SQLite now uses `/srv/data/vibebox/app.db`. Follow [the migration steps](./WEB-SECURITY.md#existing-installations) for older servers.
- **Backups aren't on until you configure them.** Edit `/etc/litestream.yml` with your bucket, then
  `systemctl enable --now litestream`.

---

## Status & honesty
- **v0.4 protects private web files.** It adds nginx deny rules, private database storage, regression checks, and normal agent permission checks.
- **v0.3 adds required security acceptance.** Complete the shared guide for server identities, provider ingress, recovery, and backup verification. The installer alone does not complete these checks.
- **v0.2 — field-validated on a fresh Hetzner CX23 + Ubuntu 26.04.** End-to-end run caught 10 empirical
  bugs that aren't in any documentation (Hetzner SKU rename, silent nginx reload failures, Litestream
  asset-name churn, apt hooks that hang on no-TTY SSH, etc.). All fixes baked in.
  See [`GUIDE.md`](./GUIDE.md) Appendix D for the full list.
- The research this is built on (levelsio's exact stack, dated sources, the funnel analysis) is in
  [`LEVELSIO-STACK.md`](./LEVELSIO-STACK.md).
- Inspired by Pieter Levels' publicly shared setup. Not affiliated with or endorsed by him.

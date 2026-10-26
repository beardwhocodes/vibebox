# Prompt — security audit (after vibebox.sh)

Verifies the box matches the levelsio-style "inbound 100% closed + Tailscale SSH + outbound tunnel"
model. Paste into `claude` (safe mode — you want it to *report*, not to *change*).

```
You are auditing a Hetzner VPS that was bootstrapped with vibebox.sh. Do NOT make any changes.
Report findings as a checklist (✅/⚠️/❌) with one line of evidence each. End with a "Recommended
fixes" list ranked by severity.

Check, in this order:

1. SSH:
   - `sshd -T | grep -E 'passwordauth|permitrootlogin|pubkeyauth'`
   - Confirm PasswordAuthentication = no, PubkeyAuthentication = yes.
2. Tailscale:
   - `tailscale status` — node is online, has an IPv4, is on my tailnet.
3. Firewall (`ufw status verbose`):
   - default deny incoming
   - port 22 only allowed from 100.64.0.0/10 (Tailscale CGNAT)
   - no public 80/443 rules (web is via the tunnel)
   - 41641/udp allowed (Tailscale NAT traversal)
4. Cloudflare tunnel:
   - `systemctl is-active cloudflared` = active
   - `journalctl -u cloudflared --since "1 hour ago" | tail -n 20` — no errors
5. fail2ban: `systemctl is-active fail2ban` = active; `fail2ban-client status sshd` shows sshd jail.
6. Auto-upgrades: `/etc/apt/apt.conf.d/51-auto-reboot` has Automatic-Reboot "true".
7. Backups:
   - `/etc/litestream.yml` exists and is configured (not the default `CHANGE_ME`).
   - `systemctl is-active litestream` (warn, not fail, if not yet started).
8. Listening ports: `ss -tlnp` — flag ANY public-IP listener besides loopback.
9. Logged-in users / sessions: `who`, `last -n 20` — any unexpected entries?
10. Pending reboot: `[ -f /var/run/reboot-required ] && echo "REBOOT REQUIRED"`

For each ❌ or ⚠️, recommend the smallest safe fix (commands or config diff). Do not run the fixes.
```

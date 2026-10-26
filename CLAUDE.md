# CLAUDE.md — Vibebox installer agent runbook

You are an AI coding agent running on the **buyer's laptop**, helping them set up
Vibebox: a hardened, agent-ready Hetzner VPS. The kit's scripts do the heavy
lifting; your job is **orchestration + secret injection + safety gates**.

> Not to be confused with [`starter/CLAUDE.md`](./starter/CLAUDE.md), which gets
> copied to `/srv/app/CLAUDE.md` ON the box for the *next* agent (the one the
> buyer uses to code their app).

## What Vibebox produces

After the installer and security acceptance checks, the Hetzner box has:
- Tailscale-only SSH (zero public inbound ports)
- Cloudflare outbound tunnel for web (no public listening ports either)
- Claude Code installed at `/usr/bin/claude` for AI-driven dev on the box
- PHP 8.5 + SQLite (WAL) + jQuery starter at `/srv/app`
- Private SQLite data at `/srv/data/vibebox/app.db`; nginx blocks private file names
- Litestream installed; a remote replica and a successful restore still need configuration and verification
- fail2ban + unattended-upgrades + auto-reboot at 04:00
- A host-firewall deadman during bootstrap; it cannot restore provider rules or tailnet policy

## Resources available to you

### Scripts in this repo
- [`vibebox.sh`](./vibebox.sh) — runs ON the box; idempotent phased lockdown. Phases: `preflight`, `base_packages`, `ssh_harden`, `tailscale_up`, `firewall`, `cloudflared_tunnel`, `fail2ban_setup`, `auto_upgrades`, `claude_code`, `app_starter`, `backups`. Plus `confirm` to cancel the deadman.
- [`packer/vibe-base.pkr.hcl`](./packer/vibe-base.pkr.hcl) — bakes a Hetzner snapshot with all software pre-installed. ~3 min.
- [`terraform/`](./terraform/) — provisions a server from the snapshot in ~40s.
- [`starter/index.php`](./starter/index.php) + [`starter/CLAUDE.md`](./starter/CLAUDE.md) — gets dropped onto the box at `/srv/app/`.
- [`demo/index.php`](./demo/index.php) — optional marketing demo page (replaces `/srv/app/index.php` with live `/proc` stats).

### CLI tools you may need
- `git`, `ssh`, `scp`, `curl`, `jq` — usually already present on macOS/Linux.
- `packer`, `terraform` — install via `brew tap hashicorp/tap && brew install hashicorp/tap/packer hashicorp/tap/terraform` if missing.
- `tailscale` (on the buyer's laptop) — `brew install tailscale && open /Applications/Tailscale.app`.
- `python3` — used for inline JSON parsing in shell.

### Buyer's env vars

The buyer should have these set in their shell session before running you:

| Var | Required | Purpose |
|---|---|---|
| `HCLOUD_TOKEN` | yes | Hetzner Cloud API token (Read+Write). Used by Packer + Terraform + direct API calls. |
| `TS_AUTHKEY` | yes | Tailscale auth key. Passed to the box via `config.env`, used by `tailscale up` in the lockdown phase. |
| `CLOUDFLARE_API_TOKEN` | only if doing public HTTPS | Needs scopes: Zone Settings:Edit, Zone DNS:Edit, Zone SSL:Edit, Account Cloudflare Tunnel:Edit. |
| `VIBEBOX_DOMAIN` | only if doing public HTTPS | The apex zone on the buyer's CF account (e.g. `example.com`). Public hostname becomes `vibebox.<this>`. |
| `ANTHROPIC_API_KEY` | optional | For non-interactive Claude Code auth on the box. Usually unneeded — buyer can SSH in and OAuth manually. |

**Verify all required vars are set before doing anything.** Don't echo them back into chat. Don't write them into git-tracked files.

## Execution plan

For existing servers, read [WEB-SECURITY.md](./WEB-SECURITY.md) before changing application or database paths.
Never create a second empty database during an upgrade.
Use `claude` with normal permission checks. Bypass requires an explicit owner request.

Walk through these phases in order. **Pause at every safety gate.** Don't auto-proceed.

### Phase 0 — Preflight & confirmation
1. Verify `HCLOUD_TOKEN` and `TS_AUTHKEY` are set; abort cleanly if not.
2. Verify `tailscale status` on the buyer's laptop returns a tailnet membership (not "Logged out").
3. Verify `packer` and `terraform` in PATH; offer to `brew install hashicorp/tap/packer hashicorp/tap/terraform` if missing.
4. Print a one-paragraph summary of what you're about to do, including the projected ~$5-7/mo recurring cost.
5. **ASK** the buyer to confirm before proceeding.

### Phase 1 — Packer snapshot (skip if one already exists)
Check existing snapshots:
```bash
curl -s -H "Authorization: Bearer $HCLOUD_TOKEN" "https://api.hetzner.cloud/v1/images?type=snapshot" \
  | python3 -c "import sys,json; r=json.load(sys.stdin); [print(i['id'], i.get('description','')) for i in r['images']]"
```
- If any snapshot has `description` matching `vibe-coding-*` and status `available`, **skip to Phase 2** (the existing snapshot is reusable).
- Otherwise:
  ```bash
  cd packer && packer init . && packer build vibe-base.pkr.hcl
  ```
  Takes ~3 min. Creates temp CX23 → snapshots it → destroys temp. Cost: ~$0.001.
  Capture the snapshot ID from the final `A snapshot was created: ... (ID: <id>)` line.

### Phase 2 — Terraform provision (creates real billable resources)
1. Get buyer's public IPv4 (force v4, ignore v6 — Hetzner's firewall CIDR can't accept a v6 host as /32):
   ```bash
   MY_IP=$(curl -4 -s ifconfig.me)
   ```
2. Get buyer's SSH public key (default to `~/.ssh/id_ed25519.pub`, fallback to `id_rsa.pub`):
   ```bash
   SSH_PUB=$(cat ~/.ssh/id_ed25519.pub 2>/dev/null || cat ~/.ssh/id_rsa.pub)
   ```
3. Write `terraform/terraform.tfvars`:
   ```hcl
   hcloud_token   = "..."        # from $HCLOUD_TOKEN
   project        = "vibebox-1"  # or vibebox-N if user has prior boxes
   ssh_public_key = "..."        # from $SSH_PUB
   admin_ip_cidr  = "<MY_IP>/32"
   server_type    = "cx23"       # or "cpx21" if buyer is in US (ash/hil) — ask if unclear
   location       = "nbg1"       # or "ash"/"hil" for US
   ```
   The `terraform.tfvars` file is gitignored — safe to write secrets to it locally.

4. **GATE — ASK BUYER:** "About to create a Hetzner CX23 in <location> (~$5/mo recurring). Confirm with `yes` to proceed."

5. After yes:
   ```bash
   cd terraform && terraform init && terraform apply -auto-approve
   ```
6. Capture `terraform output -raw server_ip`.

#### Known trap: SSH key uniqueness collision

On `uniqueness_error 409`, inspect the registered key and its Terraform ownership.
Keep recovery keys registered. Follow the SSH-key section in `terraform/README.md`.
Review the resource changes before retrying an apply.

### Phase 3 — Lockdown via `vibebox.sh`
1. Wait ~30s for the new box to finish booting:
   ```bash
   until ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 root@<IP> 'echo ok' 2>/dev/null; do sleep 5; done
   ```
2. Write a local `config.env` (don't commit it):
   ```bash
   TS_AUTHKEY="..."           # from env, never echo
   TS_HOSTNAME="vibebox-1"
   CF_TUNNEL_TOKEN=""         # populate in Phase 4 if doing tunnel
   APP_DOMAIN="vibebox.${VIBEBOX_DOMAIN:-localhost}"
   ADMIN_EMAIL="help@example.com"
   ```
3. Upload kit + config to the box:
   ```bash
   scp vibebox.sh config.env root@<IP>:~/
   ```
4. **GATE — ASK BUYER:** "About to run `vibebox.sh`. After the firewall phase fires, the box will only be reachable via Tailscale (your laptop is already on the tailnet). Deadman timer arms for 10 min as a safety net. Confirm with `yes`."

5. Run:
   ```bash
   ssh root@<IP> 'bash vibebox.sh'
   ```
6. **GATE — VERIFY TAILSCALE SSH:** Tell the buyer: *"Now open a NEW terminal on your laptop and run `ssh root@vibebox-1`. If you get a shell prompt, Tailscale is working. Tell me 'verified' and I'll cancel the deadman."* Wait for their confirmation.
7. Cancel the deadman:
   ```bash
   ssh root@vibebox-1 'bash vibebox.sh confirm' \
     || ssh root@<IP> 'bash vibebox.sh confirm'   # fallback if MagicDNS isn't resolving yet
   ```

### Phase 4 — Cloudflare tunnel + public HTTPS (skip if no `CLOUDFLARE_API_TOKEN`)

This phase is **fully API-driven**. The buyer never touches the CF dashboard. If `CLOUDFLARE_API_TOKEN` is unset, skip the entire phase and finish at Phase 5.

1. Resolve the zone ID for `VIBEBOX_DOMAIN`:
   ```bash
   ZONE_ID=$(curl -s "https://api.cloudflare.com/client/v4/zones?name=$VIBEBOX_DOMAIN" \
     -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
     | python3 -c "import sys,json; r=json.load(sys.stdin); print(r['result'][0]['id'] if r.get('result') else '')")
   ```
   If empty → abort with: "Domain `$VIBEBOX_DOMAIN` not found on your Cloudflare account. Add it first, or unset VIBEBOX_DOMAIN to skip public HTTPS."

2. Resolve account ID (needed for tunnel API):
   ```bash
   ACCOUNT_ID=$(curl -s "https://api.cloudflare.com/client/v4/zones/$ZONE_ID" \
     -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
     | python3 -c "import sys,json; print(json.load(sys.stdin)['result']['account']['id'])")
   ```

3. Create a tunnel via API:
   ```bash
   TUNNEL=$(curl -sX POST "https://api.cloudflare.com/client/v4/accounts/$ACCOUNT_ID/cfd_tunnel" \
     -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" -H "Content-Type: application/json" \
     --data '{"name":"vibebox-1","config_src":"cloudflare"}')
   TUNNEL_ID=$(echo "$TUNNEL" | python3 -c "import sys,json; print(json.load(sys.stdin)['result']['id'])")
   TUNNEL_TOKEN=$(echo "$TUNNEL" | python3 -c "import sys,json; print(json.load(sys.stdin)['result']['token'])")
   ```

4. Add Public Hostname mapping to the tunnel:
   ```bash
   curl -sX PUT "https://api.cloudflare.com/client/v4/accounts/$ACCOUNT_ID/cfd_tunnel/$TUNNEL_ID/configurations" \
     -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" -H "Content-Type: application/json" \
     --data "{\"config\":{\"ingress\":[{\"hostname\":\"vibebox.$VIBEBOX_DOMAIN\",\"service\":\"http://localhost:80\"},{\"service\":\"http_status:404\"}]}}"
   ```

5. Create the DNS CNAME (proxied):
   ```bash
   curl -sX POST "https://api.cloudflare.com/client/v4/zones/$ZONE_ID/dns_records" \
     -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" -H "Content-Type: application/json" \
     --data "{\"type\":\"CNAME\",\"name\":\"vibebox\",\"content\":\"$TUNNEL_ID.cfargotunnel.com\",\"proxied\":true,\"ttl\":1}"
   ```

6. Push the tunnel token to the box's config and restart cloudflared:
   ```bash
   ssh root@vibebox-1 "sed -i 's|^CF_TUNNEL_TOKEN=.*|CF_TUNNEL_TOKEN=\"$TUNNEL_TOKEN\"|' config.env"
   ssh root@vibebox-1 "sed -i 's|^APP_DOMAIN=.*|APP_DOMAIN=\"vibebox.$VIBEBOX_DOMAIN\"|' config.env"
   ssh root@vibebox-1 'bash vibebox.sh cloudflared_tunnel && bash vibebox.sh app_starter'
   ```

7. Verify public HTTPS:
   ```bash
   for i in $(seq 1 12); do
     code=$(curl -sI -o /dev/null -w "%{http_code}" "https://vibebox.$VIBEBOX_DOMAIN")
     [ "$code" = "200" ] && break
     sleep 10
   done
   ```
   Universal SSL provisioning can take 1–5 min on a fresh zone. If still failing after ~2 min, check `https://api.cloudflare.com/.../ssl/universal/settings` — if `enabled: false`, the zone is in the permanent-disabled state; buyer needs a fresh domain via Registrar.

### Phase 4.5 — Security acceptance

Read [SECURITY-SETUP.md](./SECURITY-SETUP.md) before declaring setup complete.
Complete its tailnet identity, network policy, provider firewall, and live access checks.
Prepare the owner-to-tag SSH rule before assigning `tag:vps` to a server.
Cancel the host deadman before closing the temporary provider SSH rule.
Read the provider state after the apply; a successful Terraform exit is insufficient.
Record backup coverage and the operator's choice about paid backups.
Report untested recovery steps and failed checks in the final handoff.

### Phase 5 — Print summary
Tell the buyer:
```
Setup complete.

Tailscale SSH:    ssh root@vibebox-1
Public web:       https://vibebox.<VIBEBOX_DOMAIN>    (or "private only" if Phase 4 skipped)
Box IP:           <IP>                                (only useful for the Hetzner panel rescue)
Starter app:      /srv/app/index.php
Run Claude Code:  cd /srv/app && claude

Monthly recurring: ~$5.73 for the box + IPv4 + snapshot. Your existing Anthropic plan covers Claude Code.
```

## Safety rules

- **Never echo secrets** back into chat. Read env vars, write to local files, scp to the box. Don't paste tokens in conversation, even if the buyer asks you to.
- **Confirm before every resource creation** in Phase 1 (Packer build), Phase 2 (Terraform apply), Phase 4 (Cloudflare tunnel + DNS).
- **During bootstrap**, the deadman can revert UFW. It cannot restore provider rules or tailnet policy. After confirmation, use the recovery steps in `SECURITY-SETUP.md`.
- **Don't `terraform destroy`** without explicit "destroy vibebox-N" instruction from the buyer.
- **Don't rotate the buyer's tokens.** That's their job, on their schedule.

## Known traps & their fixes

Earlier validation found these traps. Use the linked steps; some require manual review.

1. **Hetzner CX22 → CX23 rename** (EU-central only). For US, use `cpx21` (4 GB RAM AMD) instead.
2. **`systemctl reload nginx`** silently fails on first vhost install (EADDRINUSE). Script uses `restart` instead.
3. **Litestream `.deb` uses `x86_64` not `amd64`** in the filename. Script translates `dpkg --print-architecture` accordingly.
4. **`needrestart` hangs `apt-get install`** on no-TTY SSH. Script sets `NEEDRESTART_SUSPEND=1` globally.
5. **Old CF zones can have Universal SSL permanently disabled** — no user-recoverable fix on free plan. Buyer needs fresh domain via Registrar.
6. **`curl ifconfig.me` returns IPv6** on dual-stack; always `curl -4`.
7. **Hetzner SSH-key dedup** by public-key content; see Phase 2 known trap.
8. **Tailscale SSH periodic re-auth** is normal — buyer clicks the URL, that's it.
9. **CF Pages domain `pending`** for 1–5 min while HTTP-01 provisions a cert. Don't bail; poll.
10. **Long single-quoted SSH commands** that wrap onto multiple lines in the buyer's terminal embed real newlines and break grep. Use shorter commands.
11. **Packer HCL** multi-arg single-line blocks are invalid; templates in this repo use multi-line blocks.
12. **CF "install command" trap** — never paste the dashboard install command anywhere. Only the token goes into `config.env`. (Avoided entirely via API in Phase 4.)
13. **`IS_SANDBOX=1`** is *not* a real sandbox. Tell the buyer this if they ask about bypass mode: the safety boundary is the disposable VPS + backups, not the flag.

See [`GUIDE.md`](./GUIDE.md) Appendix D for the full empirical-bug log with code-level fix references.

## When to surface vs. soldier on

**Surface to the buyer (pause and ask):**
- Any 4xx/5xx from Cloudflare API (token scope, zone state)
- `terraform apply` errors that aren't the known SSH key collision
- Universal SSL "permanently disabled" state
- Anything that looks destructive

**Solder on quietly:**
- Retrying Packer build on transient Hetzner 5xx (max 2 retries)
- Polling for Pages cert provisioning (up to 5 min)
- Re-running idempotent script phases on transient failures (max 2 retries)

# Vibe Coding VPS Blueprint — The Guide (v0.2)

The setup and recovery guide for the @levelsio "vibe coding on
a server" workflow. Read once, run twice, ship forever.

> **Honesty up front.** This is *inspired by* Pieter Levels' publicly shared workflow.
> Not affiliated with or endorsed by him. The scripts are MIT — modify freely.

> **Field-validated on real hardware.** This kit was run end-to-end on a fresh Hetzner
> **CX23** in EU-central with **Ubuntu 26.04 LTS**. Both delivery paths were validated:
> - **Path A — `vibebox.sh` on stock Ubuntu**: ~3-5 min provisioning.
> - **Path B — Packer image + Terraform**: `packer build` in **~2m 41s**, then
>   `terraform apply` brings a fully pre-baked box up in **~40s** boot-to-ready.
>
> The validation flushed out **13 empirical bugs that aren't in any documentation** —
> Hetzner SKU renames, silent nginx reload failures, Litestream asset-name churn,
> apt hooks that hang on non-TTY SSH, Cloudflare API endpoint renames, HCL syntax
> traps, Hetzner SSH-key uniqueness collisions, and more. v0.2 has all of them baked
> in. See the bottom of this doc for the full list of what was caught.

---

## 0. Why this exists
Doing this by hand is fiddly and easy to get dangerously wrong:
- the order matters (Tailscale **before** firewall, or you lock yourself out),
- the wrong Cloudflare path leaks your origin IP,
- `IS_SANDBOX=1` is *not* a real sandbox — only a disposable VPS is,
- backups aren't an afterthought — even Levels runs **3-2-1** (Hetzner + R2 + B2 + Litestream).

This kit collapses all of that into ~5 minutes with a deadman timer that **cannot** lock you out.
You pay for **convenience and correctness**, not for the infra — Hetzner ($5–99/mo), Cloudflare
(free), Tailscale (free), and Anthropic (your plan) stay your costs, unmarked-up.

## 1. Existing tools — and why this is the complete version
Several partial overlaps exist; none combines the full 2026 stack. Honest comparison:

| | Hetzner | Tailscale + lockdown | Cloudflare **outbound tunnel** | Claude Code pre-installed | tmux + Termius mobile flow | Litestream + 3-2-1 backups | Deadman lockout guard | PHP/SQLite/jQuery starter + `CLAUDE.md` |
|---|---|---|---|---|---|---|---|---|
| **OpenClaw Terraform Hetzner** | ✅ | ✅ (optional) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Clawdbot / Mo Abukar Terraform** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **MarcoWorms/vibecoder-fullstack-vps** | ✅ | ❌ | ❌ | ✅ | partial | ❌ | ❌ | ❌ (React/Node) |
| **ashleyrudland/php-jquery-sqlite-starter** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Coolify / Dokploy** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ (PaaS) |
| **Cloudflare VibeSDK** | ❌ (CF only) | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **This kit** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

The differentiator isn't any single column — it's all of them, wired together correctly,
with the security ordering and lockout guard the partials skip.

## 2. What you'll have at the end
```
   you (laptop / iPhone)                             public internet
        │                                                  │
   Tailscale (outbound)                       Cloudflare edge (orange-cloud + tunnel)
        │                                                  │
        ▼                                                  ▼
   ┌──────────────────────────  Hetzner VPS  ──────────────────────────┐
   │  ufw: deny-all inbound; 22←Tailscale CGNAT; everything else NO     │
   │  cloudflared (outbound) ─► nginx 127.0.0.1:80 ─► PHP-FPM ─► SQLite │
   │  Claude Code on the box ─► edits files live  +  Litestream ─► R2   │
   └────────────────────────────────────────────────────────────────────┘
```

## 3. Prerequisites (≈10 minutes one-time)
- Hetzner Cloud account with a project + API token
- A domain on Cloudflare (any plan, free is fine)
- A Tailscale account
- An Anthropic plan with Claude Code access (Max or API)

---

## Part 1 — Provision the box
- Create a fresh **Ubuntu 26.04** (or 24.04) server on Hetzner. Pick by region:
  - **EU**: `CX23` (≈$4.99/mo, 2 vCPU/4 GB/40 GB, EU-central only) for experiments; `CX32` for prod.
  - **US**: `CPX11` (≈$4.35/mo, 2 vCPU/2 GB/40 GB) in `ash`/`hil`; or `CPX21` for 4 GB RAM.
  - **Cheapest absolute**: `CAX11` (ARM Ampere) — but build the Packer image on ARM too.
- **Add your SSH public key at creation** — Hetzner auto-disables password login when you do.
- Attach a Hetzner firewall. Keep a temporary SSH rule from your administrator IP during bootstrap.
- Close that rule after the checks in [SECURITY-SETUP.md](./SECURITY-SETUP.md).
- (Fast-boot path) Use the [Packer image](./packer/) to bake software in advance — server boots with
  everything pre-installed.

## Part 2 — Get the three secrets
- **Tailscale auth key**: admin → Settings → Keys → reusable.
- **Cloudflare tunnel token**: see *Cloudflare dashboard: the trap-free path* below — the
  flow has several non-obvious failure modes.
- **Anthropic**: Max plan or API key.

Put all three in `config.env` on the **box** (copy from `config.env.example`, edit interactively
over SSH). See *Token hygiene* below — secrets never go anywhere else.

## Part 2.5 — Cloudflare dashboard: the trap-free path

We learned this flow the hard way. Follow these steps exactly and you'll avoid the failure
modes we hit (stuck Universal SSL, wrong domain types, running the install command in the
wrong place, missing token scopes).

### Step 1 — Get a Cloudflare-managed domain
You need a domain whose **DNS is hosted on Cloudflare's nameservers**. There are three
flavors, and they are not equally pleasant:

| Path | Time | Pain |
|---|---|---|
| **Buy through Cloudflare Registrar** (recommended) | 2 min | Zero — domain is auto-on-CF, Universal SSL auto-enabled |
| Use a domain you already own at another registrar | 10-60 min | Update nameservers at the registrar to CF's two — wait for NS propagation |
| Use an existing CF-managed domain you already have | 0 min | Easiest if you have one |

**Buy via CF Registrar:** Cloudflare dashboard → **Domain Registration → Register Domains**
→ search a name → buy. ~$10-15/yr for `.com`, `.codes`, `.dev`. No markup. Domain appears
in your zones list within seconds and is automatically activated.

> ⚠️ **The stuck-Universal-SSL trap (real, hit us, cost hours).** If you reuse an *old*
> CF zone whose Universal SSL was ever clicked "Disable" — even years ago — it is **permanently
> disabled** on free plans. The dashboard's "Enable" button returns error 1006 in a loop. The
> API returns the same error. The only fixes are: pay $10/mo for Advanced Certificate Manager,
> open a support ticket (1-3 days), delete + re-add the zone (loses any DNS records you've
> accumulated), or **just buy a fresh domain via Registrar**. Registrar is the cheapest path
> out of this trap by an order of magnitude.

### Step 2 — Create the tunnel
**Zero Trust → Networks → Tunnels → + Add a tunnel**

- **Connector type:** Pick **Cloudflared**. (Not "WARP Connector" — that's a different
  feature for routing entire networks into your tailnet-equivalent.)
- **Name:** anything memorable, e.g. `vibebox-1`.
- **Save tunnel.**

### Step 3 — Get the token (only the token!)
The dashboard now shows you "Install and run a connector" with copy-paste install commands
for various OSes (Debian, Docker, Red Hat, Windows). **Do NOT run any of those commands.**
`cloudflared` is already on your box (`vibebox.sh cloudflared_tunnel` handled the install).

> ⚠️ **The wrong-machine trap.** It is tempting to copy the entire `sudo cloudflared service
> install <long-token>` command and paste it into your laptop's terminal. **Don't.** That
> registers your laptop as the connector for the tunnel, which is not what you want and
> leaks the token into shell history. The kit's `bash vibebox.sh cloudflared_tunnel` runs
> the install correctly on the box, using the token from `config.env`.

What you actually need: copy **only the token** (the long `eyJh...` string after `service install`).
That goes into `CF_TUNNEL_TOKEN` in `config.env` on the box. Nowhere else.

### Step 4 — Public Hostname (this is the routing rule)
**Click "Next"** → **Add a public hostname**:

- **Subdomain:** `vibebox` (or whatever)
- **Domain:** select from dropdown — must be a CF-managed zone in `status: active`
- **Path:** leave blank
- **Service:** **HTTP** → URL: `localhost:80`
  - *Why HTTP, not HTTPS?* The hop from `cloudflared` to nginx is loopback inside the box.
    Cloudflare's edge terminates TLS for you; the tunnel encrypts the edge→box hop. Adding
    TLS on the loopback hop is overhead for zero security gain.
- **Save hostname**, then **Save tunnel**.

Cloudflare auto-creates a CNAME `vibebox.<your-domain> → <tunnel-uuid>.cfargotunnel.com`
(proxied) at the same time. You don't need to touch DNS manually.

### Step 5 — Token hygiene before you paste anything
Read the *Token hygiene* section now (it's short). Then `ssh root@vibebox-1`, `nano config.env`,
paste the token into `CF_TUNNEL_TOKEN=""` and `vibebox.<your-domain>` into `APP_DOMAIN=""`,
save, run `bash vibebox.sh cloudflared_tunnel && bash vibebox.sh app_starter`.

### Step 6 — Verify
```bash
dig +short vibebox.<your-domain>          # should return CF anycast IPs (172.67.x.x, 104.21.x.x)
curl -sI https://vibebox.<your-domain>    # should return HTTP/2 200, server: cloudflare
```
You should see `<h1>It works 🚀</h1>` served via HTTPS. Done.

## Part 3 — Lock it down with `vibebox.sh`
```bash
ssh root@SERVER_IP       # over the public IP, this one last time
# upload vibebox.sh + config.env, then:
bash vibebox.sh
```
What the script does, in order, and *why the order matters*:
1. **Hardens SSH** (key-only via a `00-` drop-in that beats cloud-init).
2. **Joins Tailscale** and *verifies an IPv4* — refuses to continue if Tailscale isn't actually up.
3. **Enables `ufw` deny-all inbound** + Tailscale-only on 22 — and *arms a deadman timer* that
   auto-disables the firewall in 10 minutes unless you confirm.
4. **Cloudflare Tunnel** as a systemd service — outbound only, no public ports needed.
5. fail2ban, unattended-upgrades + auto-reboot, Claude Code, the PHP starter, Litestream.

After it finishes, **reconnect via Tailscale** (`ssh vibebox-1`) and run
`bash vibebox.sh confirm` to cancel the deadman.

Then complete [SECURITY-SETUP.md](./SECURITY-SETUP.md). The script does not configure
tailnet isolation or close the provider bootstrap rule. Verify the recovery key first.

## Part 4 — Claude Code on the box
- `claude` — safe mode (asks before edits). Use this by default.
- Permission bypass requires an explicit command. See [agent permissions](./WEB-SECURITY.md#agent-permissions).
- Drop a `CLAUDE.md` in the project root (see [`starter/CLAUDE.md`](./starter/CLAUDE.md)) — it teaches
  the agent the stack's rules so it stops fighting you with framework defaults.

## Part 5 — Edit live, or staging → prod?

The database lives at `/srv/data/vibebox/app.db`, outside the web root.
Follow [the migration steps](./WEB-SECURITY.md#existing-installations) for servers from older releases.

| Mode | Who | When |
|---|---|---|
| **Edit live on prod** | levelsio personally | You have 3-2-1 backups, you're solo, blast radius is small |
| **Staging → prod** | levelsio's advice *to others* | Any real users, anything you can't afford a 5-second blip on |

His May 11 2026 line, verbatim: *"I'm a crazy guy so I just edit production, but I have endless fail
safes and backups."* So: yes, edit-live is what he does — staging→prod is what he tells you to do.

## Part 6 — Mobile vibe coding (Termius + Mosh + tmux)
- One **Termius profile per project**, pointing at the Tailscale hostname (e.g. `nomads`, `hoodmaps`).
- Connect with **Mosh** so a flaky cellular link doesn't drop you.
- Use the `vibe` function (`packer/scripts/tmux-auto.sh`) to attach to a per-folder tmux session.
- `claude` keeps running inside the session — close the lid, reattach from the iPhone, keep going.

## Part 7 — Configure and verify backups

The installer installs Litestream. It does not create a remote replica or enable
paid Hetzner backups. Configure the actual database path, endpoint, bucket, and
credentials before starting Litestream. Restore into a separate directory and
verify the restored database before relying on it.

Choose a schedule and retention policy for off-server recovery. Provider images
and a second remote destination are optional additions. Record paid-backup
choices explicitly. A one-time local copy does not update automatically.
See [backup acceptance](./SECURITY-SETUP.md#5-record-backup-coverage-and-update-ownership).

## Part 8 — Spinning up the next project
- **One Hetzner VPS per project** (levelsio confirmed Feb 2026). Blast radius = one box.
- Same image, same `vibebox.sh`, new Tailscale hostname, new Cloudflare tunnel.
- Copy the PHP starter, point a new subdomain at the new tunnel, done.

## Part 9 — Recovery & footguns (field-tested)

Each of these is a real failure we hit during validation. The kit's v0.2 script fixes most
silently; this is for when you find yourself debugging anyway.

### SSH / Tailscale

- **Locked out of SSH?** During bootstrap, the deadman can revert UFW. After confirmation, use [the recovery steps](./SECURITY-SETUP.md#4-keep-a-usable-recovery-path). A provider rule cannot bypass UFW.
- **Tailscale SSH requests browser approval.** This is a policy `check` action. Complete the approval or use a deliberately scoped unattended rule. Tagged servers need an owner-to-tag rule; `autogroup:self` does not provide server access after tagging. Follow [the policy steps](./SECURITY-SETUP.md#1-separate-server-identities-from-personal-devices).
- **Tailscale not connecting?** Re-run `tailscale up`. Confirm the auth key isn't expired.
- **Stale known_hosts after reusing a Tailscale hostname.** First connect may print "Host key
  verification failed" if a *previous* node with the same name was in your tailnet. Fix:
  `ssh-keygen -R vibebox-1 && ssh-keyscan -H vibebox-1 >> ~/.ssh/known_hosts`.

### nginx / web

- **nginx serves the default "Welcome to nginx!" page instead of your starter** (615-byte response,
  Content-Type: text/html, `Accept-Ranges: bytes` headers). Root cause: `systemctl reload nginx`
  silently fails when the new vhost binds a more specific address (`127.0.0.1:80`) than the
  running one (`0.0.0.0:80`) — EADDRINUSE in `/var/log/nginx/error.log`, but the reload command
  returns 0 anyway. Fix: `systemctl restart nginx` (NOT reload). v0.2 of the script handles this.
- **`cloudflared` not pulling traffic.** Check `systemctl is-active cloudflared`. Then
  `journalctl -u cloudflared --since "5 min ago"` — should show "Registered tunnel connection"
  entries. Confirm the Public Hostname mapping in the CF dashboard points to `localhost:80` (NOT
  your domain — the upstream of the tunnel is the loopback service).

### apt / dpkg

- **`apt-get install ...` hangs forever on a no-TTY SSH command.** Cause: `needrestart` (a dpkg
  post-install hook on Ubuntu 22.04+) is interactively asking "which services should I restart?"
  with no TTY to answer it. Holds the dpkg lock the whole time. Fix: kill the hook from a second
  SSH terminal — `pkill -9 -f needrestart; NEEDRESTART_SUSPEND=1 dpkg --configure -a`. v0.2 of
  the script silences needrestart globally so single-phase re-runs don't hit this.
- **`apt-get` says "Could not get lock /var/lib/dpkg/lock-frontend"** — `unattended-upgrades` or
  `apt-daily.timer` is running and holds the lock. The kit waits 5 min by default
  (`DPkg::Lock::Timeout=300`). If it's still stuck after that, `systemctl stop apt-daily.timer
  apt-daily-upgrade.timer` and try again.

### Cloudflare

- **DNS doesn't resolve for `vibebox.<your-domain>`.** Likely causes, in order: (a) the domain's
  nameservers are *not* pointed at Cloudflare (verify with `dig <your-domain> NS`); (b) the
  Public Hostname mapping wasn't actually saved in the tunnel; (c) you typed the domain manually
  in the form — it has to be selected from the dropdown of CF-managed zones.
- **TLS handshake failure on a known-CF-hosted domain** (`curl: (35) ... sslv3 alert handshake
  failure`, browser says "this site can't provide a secure connection"). This is the
  **Universal-SSL-permanently-disabled** trap on old zones. Symptoms: `GET .../ssl/universal/settings`
  returns `{"enabled": false}`, dashboard "Enable" returns error 1006. Fix: see Part 2.5 — buy a
  fresh domain via Registrar. (For us this happened on a 2017-era zone where someone clicked
  "Disable Universal SSL" years prior.)
- **API returns "Unauthorized to access requested resource" (9109) or "Authentication error" (10000)**.
  Your token is missing a scope. Common needs: **Zone DNS: Edit**, **Zone SSL and Certificates: Edit**,
  **Account: Cloudflare Tunnel: Edit**.

### Agent / data

- **Agent broke something?** Restore Litestream into an isolated private directory. Verify the restored database before replacing live data.
- **The `IS_SANDBOX=1` truth.** It is *not* a real sandbox. Your safety boundary is the disposable
  VPS + the 3-2-1 backups, not the flag.

## Part 9.5 — Token hygiene

We leaked **two tokens** in our own validation session (one tunnel token, one CF API token) by
pasting CF's dashboard install command into chat instead of just copying the token. Don't repeat
our mistake.

### Rules
1. **Never paste tokens (Tailscale auth, CF tunnel, CF API) into chat, IM, ticketing systems,
   shell history, or screenshots.** Once they're in a log, they're exfiltrated.
2. **Only paste secrets into `config.env` on the box, edited interactively over SSH** (`nano
   config.env`). Do not `echo $TOKEN >>` from your laptop; that leaves them in your `.zsh_history`.
3. **Tunnel tokens are JSON base64'd** — `eyJhIjoi...` decodes to `{"a":"<account-id>","t":"<tunnel-id>","s":"..."}`.
   Anyone with the token can register a connector for that tunnel.
4. **If you leak a token, rotate it immediately:**
   - CF API token → CF dashboard → profile → API Tokens → roll/delete.
   - Tunnel token → CF dashboard → Zero Trust → Tunnels → your tunnel → refresh-token action.
     Then update `CF_TUNNEL_TOKEN` in `config.env` and re-run `bash vibebox.sh cloudflared_tunnel`.
   - Tailscale auth key → admin → Settings → Keys → revoke. (Auth keys are single-use anyway.)
5. **Shell-command hygiene over SSH.** Long single-quoted SSH commands that wrap onto multiple
   lines in your terminal embed real newlines into the remote string — which can break
   `grep "pattern" file` (the filename ends up on its own line and grep reads stdin instead).
   If a one-liner hangs after copy-paste, this is usually why. Use shorter commands.

## Part 10 — When NOT to do this
- Anything with **real user data** (PII / HIPAA / SOX / payments) before a proper security audit.
- Anything you **cannot afford to lose**, even with backups.
- Production for a business that can't tolerate a 5-second blip — use staging→prod (Part 5).

---

## Appendix A — Glossary
**Outbound tunnel** — a daemon (cloudflared / Tailscale) that opens a connection *from* your box to
a relay; inbound traffic rides back along that connection. No public listening port required.
**Deadman timer** — a scheduled job that *reverses* a dangerous change unless you cancel it.
**CGNAT range** — `100.64.0.0/10`. Tailscale assigns IPs from here; firewalling `port 22 ← CGNAT`
means SSH is only reachable from inside your tailnet.
**3-2-1 backups** — 3 copies, 2 different media, 1 offsite.

## Appendix B — Asset map
| Asset | Role |
|---|---|
| [`vibebox.sh`](./vibebox.sh) | The lockdown script (run on every new box) |
| [`packer/`](./packer/) | Bakes the base image (run once per Hetzner account) |
| [`config.env.example`](./config.env.example) | Where the three secrets go |
| [`starter/index.php`](./starter/index.php) | Single-file PHP+SQLite app, WAL, switch-router |
| [`starter/CLAUDE.md`](./starter/CLAUDE.md) | The agent rules he never published |
| [`terraform/`](./terraform/) | Optional: provision the server via Terraform |
| [`prompts/`](./prompts/) | The Claude Prompt Pack |
| [`LEVELSIO-STACK.md`](./LEVELSIO-STACK.md) | Research + funnel analysis with dated sources |

## Appendix C — Recent levelsio posts driving this kit (dated)
See `LEVELSIO-STACK.md` — fully dated, sourced, with confidence flags and known gaps.

## Appendix D — Field validation: bugs caught on real boxes (and why this matters)

v0.1 was built from research. v0.2 was forged on a real Hetzner box. The bugs below would have
hit every buyer if they hadn't been caught. None of them are findable from reading docs.

| # | Layer | Bug | Fix in v0.2 |
|---|---|---|---|
| 1 | Hetzner SKU | `cx22` instance type was renamed/replaced — Hetzner now serves **CX23** (EU-central) and **CPX11/CPX21** (US) | Defaults updated; doc steers US users to CPX |
| 2 | Ubuntu | New **26.04 LTS** released; v0.1 hardcoded 24.04 | OS image made a Packer variable; script works on either |
| 3 | Litestream | Asset URLs use the version-without-leading-v: `litestream-0.5.11-linux-...` (NOT `v0.5.11`); pattern previously worked the other way | Strip the `v` prefix when building the URL |
| 4 | Litestream | Linux asset uses arch **`x86_64`** (NOT `amd64` like dpkg reports) | Map `amd64 → x86_64` before constructing the URL |
| 5 | Litestream | Version resolution: hardcoded URLs 404 across releases | Resolve via GitHub `releases/latest` API |
| 6 | Litestream | `dpkg -i` exits non-zero on harmless post-install noise, leading to false "install failed" warnings | Verify install by `command -v litestream`, not by dpkg exit code |
| 7 | nginx | **The silent-disaster bug.** `systemctl reload nginx` returns 0 even when the new worker fails to bind (`EADDRINUSE` because the running default site holds `0.0.0.0:80`). Vhost looks installed, box still serves the welcome page. | Use `systemctl restart nginx` on first setup; assert service is active afterward |
| 8 | cloudflared | Phase bailed out entirely if `CF_TUNNEL_TOKEN` was empty — leaving the binary uninstalled, so subsequent re-runs after getting a token still had to re-do the apt install | Split: install the binary always (no secret needed), only the tunnel-configure step gates on the token |
| 9 | apt | Single-phase re-runs of the script skipped `base_packages` and therefore the `DEBIAN_FRONTEND=noninteractive` export — apt then hung on debconf prompts or dpkg-lock contention | Set both `DEBIAN_FRONTEND=noninteractive` and `DPkg::Lock::Timeout=300` globally at script top |
| 10 | apt | **`needrestart` hook on Ubuntu 22.04+ hangs `apt-get install` forever** in non-TTY SSH command mode — it has its own interactive prompt that ignores `DEBIAN_FRONTEND`. Holds the dpkg lock. | `NEEDRESTART_MODE=a` + `NEEDRESTART_SUSPEND=1` exported globally |
| 11 | Packer | Multi-argument single-line blocks (`variable "X" { type = string default = "Y" }`) are invalid HCL; `packer validate` errors with "single-line block definition must end with a closing brace immediately after its single argument" | All multi-arg blocks expanded to multi-line |
| 12 | Terraform | Hetzner deduplicates SSH keys by **public-key content** (not name). Re-applying Terraform when the same key already exists in the project errors with `uniqueness_error 409` | Documented in `terraform/README.md` with a quick-unblock command; deleting the key from Hetzner has no effect on existing servers |
| 13 | Terraform | `curl ifconfig.me` returns IPv6 on dual-stack networks; `<v6-address>/32` is **not** a valid CIDR (it isn't the start of `<v6>::/32`). Hetzner Firewall validation rejects it | Doc steers users to `curl -4 ifconfig.me` for IPv4 + `/32`; falls back to `/128` for IPv6 |

Found deploying the first **real** app onto a box (a Composer/front-controller PHP app, not the jQuery starter):

| # | Layer | Bug | Fix |
|---|---|---|---|
| 14 | App deploy | The image ships **minimal PHP** for the starter: `pdo_sqlite` yes, but **no `mbstring`, no `curl`, no Composer**. A real Composer app then fails `composer install` (platform-req check) and breaks runtime HTTP (OAuth / Places / API calls) | `apt-get install -y php8.5-mbstring php8.5-curl` (under `NEEDRESTART_SUSPEND=1`) + install Composer. Then point nginx `root` at the app's `public/` — the starter vhost's `try_files $uri $uri/ /index.php?$query_string` front-controller already fits, so only the `root` line changes |
| 15 | Litestream | Box runs **0.5.x** (a rewrite). `litestream snapshots` / `generations` are **gone** (`unknown command`) — inspect with `litestream ltx <db>`. Retention is no longer per-replica: it's **global** top-level `snapshot:` (`interval` + `retention`) and `retention:` (`enabled`) blocks, and durations are **s/m/h only** (no `d`/`w`) | Put retention in the global blocks; restart and assert the unit is `active` (a bad key fails the service — back up the yaml + auto-rollback). Working R2 replica: `type: s3`, `endpoint: https://<acct>.r2.cloudflarestorage.com`, `region: auto`, `force-path-style: true` |
| 16 | Cloudflare R2 | For Litestream→R2: R2 must be **enabled once in the dashboard** (accept terms + card on file) before *any* R2 API works — otherwise bucket-create returns `code 10042 "Please enable R2…"` (no API path). And a `Workers R2 Storage` **management** token is **not** usable as S3 credentials | Enable R2 in the dash once. Mint an R2 token with the **object** permission groups (`Workers R2 Storage Bucket Item Read` + `…Write`); then S3 **Access Key ID = the token's `id`**, **Secret Access Key = SHA-256(token value)**. Fully scriptable via `POST /accounts/{id}/tokens` when the CF token also holds `Account: API Tokens` + `Account: Workers R2 Storage: Edit` |
| 17 | Tailscale / deploy | An SSH policy `check` action can wait for browser approval during an unattended transfer. | Complete the approval or configure a deliberately scoped owner-to-tag rule. Follow [security acceptance](./SECURITY-SETUP.md); tagged servers are not personal devices under `autogroup:self`. |

Plus operational learnings (not code-level bugs but worth knowing):

- Cloudflare zones can have **Universal SSL permanently disabled** on free plans with no
  user-recoverable path. Fix: fresh domain via Registrar.
- Adding a tunnel Public Hostname via API requires the **Account: Cloudflare Tunnel: Edit**
  scope, not just zone-level scopes.
- Cloudflare's tunnel install command in the dashboard is misleading — it's meant to be a
  copy-paste install for the OS, but if the kit already installed cloudflared on the box,
  you only need the **token** (the `eyJh...` string after `service install`). Don't paste
  the rest anywhere.
- Tailscale SSH has **periodic re-auth** on tailnets with the "check" ACL action — the
  `login.tailscale.com/a/...` URL is normal, not a bug.
- Long single-quoted SSH commands wrap in terminals and **embed newlines** that break
  `grep` (filename ends up on a new line, grep reads stdin instead). Use shorter commands.
- The public hostname is **parameterizable** — `APP_DOMAIN` + the tunnel ingress hostname +
  the DNS CNAME `name` can be any subdomain; the runbook's `vibebox.<domain>` is just a
  default (a real project shipped at `meet.<domain>` by setting those three to `meet`).
- `litestream restore -o <out> <db-path>` reads the replica from `/etc/litestream.yml`, so
  config-based restore needs no bucket URL on the command line.

## Appendix E — License + disclaimers
Scripts and templates: **MIT.** Modify freely.
**Not affiliated with @levelsio.** "Vibe coding" is Andrej Karpathy's term; the workflow is Pieter
Levels'. This kit is independent packaging of public information, with security/lockout corrections
the originals don't include.
Use at your own risk. Strong backups required. Hire a security auditor before real user data.

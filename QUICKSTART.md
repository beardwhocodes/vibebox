# Vibebox — quick start (agent-driven, ~10 min)

The fast path: **your AI agent runs the kit for you.** You generate three auth
tokens, paste a prompt, and confirm at three safety gates. The agent calls every
API directly — you never touch the Cloudflare dashboard, never copy a tunnel
token, never SSH manually.

> Prefer to do it by hand? [`GUIDE.md`](./GUIDE.md) walks the same flow manually.

---

## Prerequisites (~5 min)

You need three things on your laptop:

1. **Claude Code** (or Cursor, or any agent that reads `CLAUDE.md`) — likely already installed if you bought this kit.
2. **Three auth tokens** (each takes ~60 seconds to generate; see below).
3. **Tailscale running** on your laptop — `brew install tailscale && open /Applications/Tailscale.app`, sign in.

Don't paste tokens into chat or shell history. Set them as env vars in your shell:

```bash
# Open a fresh terminal session and paste these one at a time
export HCLOUD_TOKEN='...'
export CLOUDFLARE_API_TOKEN='...'   # optional; only if you want public HTTPS
export TS_AUTHKEY='tskey-auth-...'
export VIBEBOX_DOMAIN='your-domain.com'   # optional; subdomain becomes vibebox.<this>
```

### Generating the tokens

#### 1. Hetzner Cloud API token

Hetzner Cloud Console → your project → **Security → API Tokens → Generate API Token**.
- **Permissions:** *Read & Write*
- Copy the token (string of random letters)
- `export HCLOUD_TOKEN='...'`

#### 2. Cloudflare API token (only if you want a public domain)

CF dashboard → top-right avatar → **My Profile → API Tokens → Create Token → Create Custom Token**.
- **Token name:** anything (`vibebox-installer`)
- **Permissions** (click "+ Add more" for each):
  - **Zone** → **Zone Settings** → **Edit**
  - **Zone** → **DNS** → **Edit**
  - **Zone** → **SSL and Certificates** → **Edit**
  - **Account** → **Cloudflare Tunnel** → **Edit**
- **Zone Resources:** Include → Specific zone → *your domain*
- **Account Resources:** Include → your account
- Create → copy the token
- `export CLOUDFLARE_API_TOKEN='...'`

> No domain on Cloudflare yet? **Buy one through Cloudflare Registrar** in the
> same dashboard (~$10–15/yr) — Universal SSL is auto-enabled and there's no
> nameserver-switching pain. Or skip this and run the box without public HTTPS
> for now (it's still useful as a Tailscale-only agent dev box).

#### 3. Tailscale auth key

Tailscale admin → **Settings → Keys → Generate auth key**.
- **Reusable:** off (single-use is fine)
- **Expiration:** 90 days (default)
- Copy the `tskey-auth-...` value
- `export TS_AUTHKEY='tskey-auth-...'`

---

## Step 1 — Clone + open in your agent

```bash
git clone git@github.com:beardwhocodes/vibebox.git
cd vibebox
```

Open the folder in Claude Code (or your agent of choice).

## Step 2 — Paste this prompt

```
Read CLAUDE.md at the repo root and set up Vibebox for me.

I have these env vars set (from my shell, do not echo them back):
- HCLOUD_TOKEN
- TS_AUTHKEY
- CLOUDFLARE_API_TOKEN  (or unset, if I'm skipping public web)
- VIBEBOX_DOMAIN        (or unset)

Use the Packer + Terraform path for fastest spin-up. Pause and ask for
confirmation at each safety gate before creating real resources or running
the firewall lockdown.
```

## Step 3 — Confirm at three gates

The agent will pause and ask for your "yes" at three points:

| Gate | What happens if you say yes | What to do |
|---|---|---|
| **Before `terraform apply`** | Creates a Hetzner CX23 (~$5/mo) from the pre-baked snapshot | Type `yes` |
| **After firewall phase** | Tailscale SSH must work before the deadman fires. Open a **new terminal** and run `ssh root@vibebox-1`. If it prints, you're good. | Confirm to the agent so it cancels the deadman |
| **Before CF tunnel (only if domain set)** | Creates a Cloudflare tunnel + DNS record for `vibebox.<your-domain>` | Type `yes` |

If the second SSH doesn't work for any reason, **do nothing for 10 minutes** —
the deadman timer reverts UFW during bootstrap. This works only while the provider
rule and OS network configuration still permit the initial SSH path.

## Step 4 — Finish security acceptance

Complete [SECURITY-SETUP.md](./SECURITY-SETUP.md) before declaring the server ready.
It adds separate server identities, selected administrator access, closed provider ingress,
and live checks. Verify the recovery key before closing the bootstrap rule.
An enrollment key alone cannot change the tailnet policy.
Record the backup choice; paid provider backups stay off unless approved.

## What you get

After the agent finishes (~10 min wall time, mostly waiting):

- **Box:** Hetzner CX23 at `<IP>`, accessible only via Tailscale SSH (`ssh root@vibebox-1`)
- **Public web (if CF set up):** `https://vibebox.<your-domain>` serving the PHP+SQLite starter
- **Agent on the box:** `cd /srv/app && claude` — Claude Code with stack-aware rules
- **Backups:** Litestream ready (point `/etc/litestream.yml` at your R2/B2 bucket)
- **Cost:** ~$6–8/mo (Hetzner + IPv4 + snapshot storage); your existing Anthropic plan covers Claude Code

## Cost breakdown

| Item | Cost |
|---|---|
| Hetzner CX23 (or CPX21 if you're in the US) | $4.99–$7.55/mo |
| 1 × IPv4 address | $0.73/mo |
| Snapshot storage (~1 GB) | $0.01/mo |
| Cloudflare (tunnel + DNS + Universal SSL) | free |
| Tailscale (personal) | free |
| Domain (optional) | ~$10–15/yr via CF Registrar |
| **Total infra** | **~$6–8/mo** |

The kit is one-time.

## When things go sideways

The agent reads `CLAUDE.md` (which references `GUIDE.md` Appendix D for the full
field-tested troubleshooting). In rough order, when stuck:

1. **Read the agent's last output.** Usually says what failed and what to do.
2. **During initial bootstrap**, the deadman can revert UFW. It cannot restore provider rules or tailnet policy.
3. **After confirmation**, use [the recovery steps](./SECURITY-SETUP.md#4-keep-a-usable-recovery-path). Opening provider port 22 alone does not bypass UFW.
4. **[`GUIDE.md`](./GUIDE.md) → Part 9** has the field-tested recovery playbook
   for every failure we hit during validation.

If the agent gets stuck on something un-documented, paste the error to the buyer
Discord (link in your purchase receipt).

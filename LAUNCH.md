# Launch kit — teaser thread + landing page copy

> Tone: pragmatic, anti-fluff, concrete. The audience is the indie-hacker / @levelsio
> crowd — they smell marketing-speak instantly. Show numbers and screenshots, not adjectives.
> **Do not imply endorsement by @levelsio.** Call yourself "inspired by," credit him, link him.

---

## A. Teaser thread (X, ~8 tweets)

> Use it to gauge demand before you finish polishing. Add 2-3 screenshots: a tmux session with
> `claude` running on the box, a ufw status showing `22 (Tailscale) only`, a Cloudflare tunnel
> dashboard with "Healthy", a Hetzner project with 3 servers each on its own VPS.

**1/ (hook)**
Everyone wants to vibe-code on a Hetzner box like levelsio.

Almost everyone sets it up wrong.

You either expose SSH to the world, leak your origin IP through Cloudflare, skip backups, or — most fun — *lock yourself out of the box on step 4*.

**2/ (pain)**
Hand-rolled, the levelsio workflow is ~17 manual steps across 4 services, and **the order matters**.

Tailscale before firewall. Backups before the agent gets bypass mode. SSH drop-in named `00-` to win precedence over cloud-init.

I bricked two boxes learning this.

**3/ (what I built)**
So I built it once, correctly, and packaged the whole thing:

• One `bash vibebox.sh` to lock the box down
• A Packer template to pre-bake the image
• Litestream backups pre-wired (he runs 3-2-1; this gives you a sane default)
• A `CLAUDE.md` for the PHP/SQLite stack he never published

**4/ (the killer feature)**
The script has a **deadman timer** on the firewall.

It auto-disables the firewall in 10 min unless you confirm from a *new* Tailscale SSH session.

You cannot lock yourself out. The single scariest part of doing this by hand becomes impossible.

**5/ (correctness, plainly)**
A few corrections vs. the guides floating around:

• levelsio uses the **outbound tunnel** model now (May 2026) — inbound 100% closed.
• `IS_SANDBOX=1` is *not* a real sandbox. Your safety boundary is the disposable VPS.
• Snapshots are project-scoped — nobody can hand you a ready Hetzner image. You bake your own.

The kit ships the Packer config so you do.

**6/ (cost honesty)**
I don't mark up Hetzner, Cloudflare, Tailscale, or Anthropic. Those stay your costs (~$5–$99/mo for the box, free tiers for the rest, your existing Claude plan).

You're paying for the **convenience** and the **correctness** — once.

**7/ (what's in it)**
• Step-by-step guide (Hetzner → lockdown → tunnel → Claude → mobile → backups)
• `vibebox.sh` (idempotent, with the deadman)
• Packer template + scripts (image bakes the slow stuff)
• Terraform module (one `apply` to a fresh box)
• A Claude Prompt Pack for common server tasks
• PHP/SQLite/jQuery starter + `CLAUDE.md`

**8/ (CTA)**
Live demo on a real box: https://vibebox.beardwho.codes
Get the kit: https://beardwho.codes  ($9.99 Standard / $59.99 Pro)

Inspired by @levelsio's publicly shared workflow. Not affiliated.

---

## B. Landing page copy

### Hero
> ## Spin up a secure, agent-ready VPS in one command.
> The @levelsio "vibe coding on a server" workflow — packaged, hardened, lockout-proof.
> One bash script + a Packer image + a guidebook. ~5 minutes, ~5 commands.
>
> [ Get the kit — $9.99 ]   [ See what's inside ]

*Inspired by Pieter Levels' publicly shared workflow. Not affiliated with or endorsed by him.*

### The pitch (three plain sentences)
1. The levelsio Hetzner stack is **cheap and powerful** — but the manual setup has ~17 ordered steps across 4 services and a real risk of locking yourself out.
2. This kit collapses it to **one script + one image**, with a **deadman timer** that can revert the host firewall during bootstrap, **Litestream ready for remote backup configuration**, and the **outbound tunnel** model that hides your origin entirely.
3. You pay **once** for the convenience. Hetzner ($5–99/mo), Cloudflare (free), Tailscale (free), and Anthropic (your plan) stay your costs — we don't mark any of them up.

### What's inside

#### Step-by-step guidebook
- Hetzner provisioning (CX23 EU / CPX11 US → CX32, ARM CAX, SSH key, panel firewall as out-of-band rescue)
- Tailscale → ufw lockdown in the order that doesn't lock you out
- Cloudflare Tunnel (outbound) so your origin has zero public ports
- Claude Code on the box: safe mode vs. bypass mode, and when to use which
- Termius + Mosh + tmux for laptop ↔ iPhone vibe coding
- Litestream + R2 + B2 for real 3-2-1 backups
- Recovery & footguns (locked out, agent broke prod, tunnel down)

#### Pre-built scripts
- `vibebox.sh` — one idempotent, phased, deadman-guarded lockdown
- Packer template + scripts to bake your own Hetzner image
- Terraform module to provision a server from that image in one `apply`
- Litestream `litestream.yml` template for R2
- A safe `tmux` per-folder session helper (no `cd` hijack, no recursion)

#### The Claude Prompt Pack
Battle-ready prompts for the box: security audit, new-project bootstrap, agent kill-switch,
SQLite migration with backup, "continue from CLAUDE.md" autopilot, mobile debugging, more.

#### The starter
A single-file PHP + SQLite + jQuery app with WAL mode, switch-router, and a `CLAUDE.md`
tuned to the stack so the agent stops fighting you with framework defaults.

### Why this beats existing tools
| | Other Terraform + Tailscale starters | This kit |
|---|---|---|
| Tailscale + firewall | ✅ | ✅ |
| Outbound tunnel | ❌ | ✅ |
| Claude Code pre-installed | ❌ | ✅ |
| Litestream backups | ❌ | ✅ |
| Deadman lockout guard | ❌ | ✅ |
| Stack-tuned `CLAUDE.md` | ❌ | ✅ |
| Mobile workflow | ❌ | ✅ |

### Pricing
| | **Standard — $9.99** | **Pro — $59.99** |
|---|---|---|
| Guidebook + scripts | ✅ | ✅ |
| Packer template + Terraform module | ✅ | ✅ |
| Prompt Pack | ✅ | ✅ |
| Lifetime updates | — | ✅ |
| Private community / Discord | — | ✅ |
| Bonus stack starters (Laravel, Node) | — | ✅ |

*MIT-licensed scripts. One-time purchase. Refund if you can't make it work.*

### FAQ
**Is this affiliated with levelsio?** No. It's *inspired by* his publicly shared workflow.
He hasn't endorsed it and we don't claim he has.

**Is `IS_SANDBOX=1` actually a sandbox?** No. The kit is explicit about this: real isolation
comes from the **disposable per-project VPS**, not the flag. Bypass mode is for boxes you can
afford to lose.

**Will my server get cryptomined?** Not from the open-ports vector — the kit closes all public
inbound ports and forces traffic through Cloudflare's outbound tunnel. (Cryptominers find boxes
the same way every time: a forgotten open port. There isn't one here.)

**Production-ready?** For your side project, yes. For real user data / regulated workloads,
hire a security auditor before going live — exactly as levelsio himself recommends.

**What if Hetzner snapshots are project-scoped?** They are. That's why the kit ships the
**Packer template** so you bake your own image in your own account. There's no "import this
snapshot" shortcut on Hetzner — anyone selling you one is misleading you.

### Final CTA
[ Get the kit — $9.99 ]   *Pro tier: $59.99*

---

## C. Notes for the launcher
- **Validate before polishing.** Post the thread, watch reply volume + DM volume, only spend
  another weekend on it if the signal is real.
- **Don't fake testimonials.** Use your own screenshots and a real "I tested it on a throwaway
  Hetzner project, ssh'd in over Tailscale, served traffic over the tunnel, the box has zero
  public listening ports — proof:" thread instead.
- **Anti-affiliation hygiene.** Every mention of levelsio includes "not affiliated." Don't use
  his face. Don't use his logo. Don't claim his endorsement.
- **Refund honestly.** A real refund policy converts indie-hacker buyers; faked or stingy ones
  destroy trust on X overnight.
- **Track which preview channel converts.** If Twitter thread → Gumroad → buy, note the entry
  point. The convert-rate from a viral thread tells you whether to repeat the format.

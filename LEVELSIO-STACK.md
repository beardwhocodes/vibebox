# The levelsio Vibe-Coding VPS Stack — Reference + Productization Brief

> Synthesis of Pieter Levels' (@levelsio) publicly-stated setup for running AI coding agents on a
> secure VPS, recency-weighted to **2025–2026**. Older opinions flagged as outdated.
> Compiled 2026-05-25. Sources cited inline; X quotes reconstructed from search snippets (X is
> paywalled to fetchers) — wording faithful, verify punctuation against the live tweet if it matters.

---

## Research method & confidence — READ THIS FIRST (it has limits)
This was assembled **without authenticated access to X**. Direct fetches of x.com return `402 Payment
Required`, so all tweet content came *indirectly* via (a) search-engine snippets and (b) a reader proxy
(r.jina.ai). That method has **two structural blind spots**, both confirmed in practice:
- **Recency lag** — brand-new tweets aren't crawled yet (his May 26 2026 outbound-tunnel tweet was invisible
  until its ID was supplied directly).
- **Popularity bias** — search surfaces tweets that got linked/quoted/went viral; a low-engagement but
  technically-important infra tweet may never appear. (Proof: a later keyword pass surfaced the "Daniel
  Lockyer / DevOps guy" tweet that the initial sweep missed entirely.)
The reader proxy can read **a specific tweet by ID** reliably, and his **profile top** (a snapshot only) —
but it **cannot enumerate his full timeline**. So: treat single-source claims as provisional;
multi-corroborated claims as solid. **The reliable path to completeness is an on-platform/authenticated
tool (e.g. Grok with live X access) or pointing me at specific threads/IDs.** Confidence is flagged
throughout; the §1 table and "gaps" lists mark what is/ isn't corroborated.

---

## 0. The one-paragraph version

Levels rents a **Hetzner VPS** (DigitalOcean as the beginner-equivalent), runs **vanilla PHP + jQuery
+ SQLite on nginx with no build step**, and — the 2025–2026 shift — installs **Claude Code directly on
the server** and codes by SSH (from a laptop *or* an iPhone). He secures the box with a
**Tailscale-for-SSH + Cloudflare-for-web + deny-everything-else firewall** model. He calls the workflow
"**raw dog dev on the server**" / "**vibeservering**," runs the agent in bypass-permissions mode, skips
the deploy step entirely (the agent edits live), and isolates blast radius by giving **each project its
own disposable VPS**. He explicitly argues his "dumb" simple stack is *better* for AI because the agent
makes fewer mistakes with no framework/build complexity.

---

## 1. Infrastructure layer

| Choice | What he uses | Confidence / date |
|---|---|---|
| **Host** | **Hetzner** (primary). DigitalOcean offered as the interchangeable beginner alt ("Hetzner calls it a server, DO calls it a droplet"). | High — 2025/2026 |
| **Tiers** | **$5/mo VPS** for experiments & "raw dog dev"; **$99/mo Hetzner** runs RemoteOK in prod; a **dedicated Hetzner box** for going deep/learning. | High — Aug 2025–Sep 2025 |
| **OS** | **Ubuntu LTS**. (24.04 is community-inferred from setups built on his advice; he just says "LTS".) | Med |
| **Web server** | **nginx + PHP-FPM**. nginx config as the per-domain router. No Caddy/Apache. | High |
| **Edge** | **Cloudflare free plan** in front of everything, full-page edge caching → RemoteOK loads in 27ms. | High — Sep 2025 |
| **Topology** | **Single server per product**; one big box runs a site, survives HN/PH spikes. In the AI era he runs **one isolated VPS per project** so a blown-up agent only takes down one thing. | High |

**He is NOT purely solo on infra (corrected Aug 2024 finding):** *"Not serverless, not edge, not kubernetes,
not auto scaling. Just 1 simple VPS for all my sites. Thx to @DanielLockyer my DevOps guy."*
(x.com/levelsio/status/1827305940418187669). So the "anyone can self-run this" framing has an asterisk —
**even Levels pays a DevOps person to manage his server.** This *strengthens* the convenience-product case.
He also tells beginners: *"get a security/server guy to check your set up so it's safe for production"*
(x.com/levelsio/status/1953060243241398447, Aug 2025).

**Backups / DR — he has a serious strategy (corrected May 2026, authoritative source):** *not* a gap as
earlier drafts claimed. He runs a **3-2-1+ scheme — "one live copy, one local backup, one off site backup,
another off site backup"** — using **Hetzner snapshots + Cloudflare R2 + Backblaze B2 + Litestream** (DBs
"starting to be Litestream'd to another R2 bucket"). (x.com/levelsio/status/2052867757956354478 May 8;
…/2058149083001286829 May 23.) Reliability claim: *"Claude Code only failed me 2x in 12 months… site was
down for 2x 5 seconds. It never lost any data."*

**Per-site VPS isolation — confirmed (Feb 2026):** *"Each of my sites is now on their own Hetzner VPS"*
(e.g. hoodmaps.com has its own box running Claude Code + a Telegram-bot daemon).
(x.com/levelsio/status/2024507875356279026)

**Still genuinely unconfirmed:** exact Hetzner instance SKU (CPX/CCX/CAX/AX102 — never named), ARM-vs-x86
opinion. (DigitalOcean has dropped out of his 2026 posts — now effectively **Hetzner-only**.)

**Outdated (do not rely on):** the famous 2014 blog "How I build my MVPs" (Linode $40/mo, Ubuntu 14.04,
Namecheap DNS, JSON flat-files, S3 backups). Trajectory: **Linode (2014) → DigitalOcean (~through 2024)
→ Hetzner (2024–2026)**.

---

## 2. Application layer

| Layer | What | Notes |
|---|---|---|
| **Language** | **Vanilla PHP, no framework** (no Laravel/Symfony) | Photo AI ≈14k lines of PHP, HTML inline, no MVC |
| **Frontend** | **jQuery + raw vanilla JS** in `<script>` tags | No React/Vue/Next/Svelte/TS |
| **DB** | **SQLite for everything** | WAL mode + indexes; Photo AI's db reportedly grew to **162GB** and "works flawlessly"; he cites SQLite's 281TB ceiling and refuses to migrate to MySQL/Postgres |
| **Build step** | **None** | This is now his explicit *AI* advantage, not just a preference |
| **Rendering** | Server-rendered PHP monolith | "single index.php" is a real pattern + partly a flex |
| **Payments** | **Stripe** | started products with a Stripe link + manual fulfillment |
| **Auth** | **Custom PHP** | no Auth0/Clerk/NextAuth |
| **Email** | Cost-driven toward **Amazon SES / Cloudflare Email** (benchmarked SES at ~$100 vs Postmark $1,206 per 1M emails, Oct 2025) | |
| **Storage** | **Cloudflare R2** for all generated images/blobs; only metadata in SQLite | |
| **AI compute** | **Replicate** (SDXL/DreamBooth) for Photo AI, ~$12k/mo GPU | |

**The 2025 thesis (most important quote for productization):**
> *"Ironically my super simple code base with just PHP, JS, jQuery, SQLite is excellent for AI coding
> because it's so basic, simple and 'dumb'. No complicated things so AI fucks up less."* — @levelsio, Jul 13 2025

This inverts the 2023-era "AI loves TypeScript/React" assumption: **no build step + flat PHP = more
reliable agents.**

**Evidence the stack works:** ~**$3.1M+/yr solo**, zero employees, ~80–87% margins. Photo AI ~$132–138K/mo,
Nomads ~$38–61K, Remote OK ~$35–41K, Interior AI ~$38–45K. Peak Sept 2024 ≈ **$420K/mo total**.

---

## 3. Security layer — the "hyper secure" part

His model evolved. **Most recent = authoritative.**

**Phase 1 (Aug 2025) — "HOW TO SECURE YOUR RAW DOG VPS SERVER":** key-only SSH (no passwords) · fail2ban
· unattended-upgrades **with auto-reboot** · enable the host-panel firewall · Let's Encrypt/certbot ·
"always hire a security auditor before real user data / production."

**Phase 2 (early 2026) — Tailscale-first, the current canonical recipe:**
> *"When I set up a new Hetzner VPS first thing I do install Tailscale and once I'm in via Tailscale lock
> down the firewall to only accept web traffic on HTTPS 443 for Cloudflare IPs and SSH 22 for Tailscale
> IP. That way nobody can get in."* — @levelsio, Mar 16 2026

| Layer | His rule |
|---|---|
| SSH auth | **Key-only, password login never** (Hetzner auto-disables passwords when you add a key at creation) |
| SSH exposure | Port **22 only via Tailscale** (private WireGuard net, CGNAT `100.64.0.0/10`) — not on the public internet |
| Web ingress | Port **443 only from Cloudflare IP ranges** (hides origin, forces all traffic through CF) |
| Firewall | Host-panel firewall (Hetzner) + default-deny; treated as the **out-of-band fallback** if Tailscale dies |
| Brute force | **fail2ban** on SSH |
| Patching | **unattended-upgrades + auto-reboot** |
| Fallback | If Tailscale is down: open 22 to *his own IP* via the Hetzner panel firewall |
| Personal flourish | An **obscure SSH port** ("I like security by obscurity") — optional |
| Automation | **"Install Claude Code and just ask it to do all these things."** |

### His view evolved again — now it IS the outbound tunnel (May 2026)
Earlier in 2026 (Mar 16) his stated setup was Cloudflare **proxy** with the firewall restricting 443 to
Cloudflare's CIDRs — origin still listening on 443. **By May 26 2026 he advocates the full outbound-tunnel
model** (verbatim):
> *"Both Tailscale and Cloudflare make an outbound tunnel which once it's set up also receives traffic...
> That means your inbound firewall can be 100% fully blocked and not accept anything... Imagine you make a
> call to your friend (outbound), while in that convo he tells you if someone is looking for you and
> forwards your messages... But you don't accept phone calls yourself anymore! That's an outbound tunnel."*
> — @levelsio, status 2057920580913959419

So the **current** model is: **inbound firewall 100% closed**, web traffic arrives via a Cloudflare
outbound tunnel (`cloudflared`-style) and admin via Tailscale's outbound tunnel — no public listening port
at all. This is *stronger* than the Mar 2026 "443-from-Cloudflare-IPs" recipe and supersedes it. (It also
means the "true tunnel = a feature you add beyond him" framing in earlier drafts is wrong — he's there now.)

---

## 4. AI-coding workflow — "raw dog dev on the server"

- **Install the agent ON the VPS:** `apt install npm` → `npm install -g @anthropic-ai/claude-code` → `claude`.
- **SSH client: Termius** (desktop **and iOS**). **Mosh** for resilient transport + **tmux** for a
  persistent session that survives disconnects → he literally **codes from his iPhone** ("while gf is shopping").
- **Bypass-permissions mode** (his current default, early 2026):
  ```bash
  c() { IS_SANDBOX=1 claude --dangerously-skip-permissions "$@"; }
  ```
  Result: ~**10x output**, "outran my todo list," new bottleneck is his own creativity.
- **No deploy step — but note the asterisk on his advice.** He personally edits **production live, no
  staging**, feedback loop = edit → refresh the tab. But his *recommendation to others* (May 11 2026) is the
  safer version: *"get a VPS you can code from and then use as staging and test and deploy from there to
  your production server. I'm a crazy guy so I just edit production, but I have endless fail safes and
  backups."* (x.com/levelsio/status/2053772235664486637). So: **edit-live is his personal move; staging→prod
  is what he tells you to do** — backed by the 3-2-1 backups above.
- **Workflow polish (May 2026):** Termius **profiles per site** + a **tmux folder-based auto-session
  script** (Claude Code wrote the bash function) so each site is a tab; seamless laptop↔iPhone switching.
  (…/2057933239600263582, …/2057939237303914660). Mosh + Tailscale + plain SSH to the Hetzner box.
- **Guardrails he actually relies on:** one **isolated VPS per project**, **backups**, **write tests and
  let it run freely**, harden the box. He warns *others*: **"DO NOT RUN [bypass] ON PRODUCTION!!!! JUST ON
  YOUR FUN VPS"** — while himself running it on his own non-critical, isolated, backed-up prod. Reconcile
  accordingly.

### ⚠️ Second correction
`IS_SANDBOX=1` is **not** a real sandbox — security researchers have shown Claude Code's denylist sandbox
is bypassable. The *real* isolation in his setup is **the disposable single-purpose VPS**, not the flag.
Any product must not market `IS_SANDBOX=1` as actual containment.

**Gaps:** he never names a model SKU (Opus vs Sonnet — just "Claude Code"); no public `CLAUDE.md`,
cursor-rules, or dotfiles repo exists; no published comparison of Claude Code vs Codex/Windsurf in 2026.

---

## 5. The copy-paste recipe (his rules, community-implemented)

> He delegates the actual commands to Claude Code and never posted a script. Below implements *exactly*
> his stated rules; **verify before running.** Order matters — **join Tailscale BEFORE locking the
> firewall** or you lock yourself out.

```bash
# 1. Join the private network FIRST
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up                       # approve the printed URL in a browser

# 2. Harden SSH  (/etc/ssh/sshd_config)
#    PasswordAuthentication no
#    PermitRootLogin prohibit-password
#    PubkeyAuthentication yes
#    # Port 2222   <- his optional "obscure port" flourish
sudo systemctl restart ssh

# 3. Firewall: deny all, then 22←Tailscale only, 443←Cloudflare only
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from 100.64.0.0/10 to any port 22 proto tcp        # SSH via Tailscale
sudo ufw allow 41641/udp                                          # Tailscale NAT traversal
for ip in $(curl -s https://www.cloudflare.com/ips-v4); do sudo ufw allow from $ip to any port 443 proto tcp; done
for ip in $(curl -s https://www.cloudflare.com/ips-v6); do sudo ufw allow from $ip to any port 443 proto tcp; done
sudo ufw enable

# 4. Brute-force + auto-patch
sudo apt install -y fail2ban unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
#    in /etc/apt/apt.conf.d/50unattended-upgrades:
#    Unattended-Upgrade::Automatic-Reboot "true";
#    Unattended-Upgrade::Automatic-Reboot-Time "04:00";

# 5. The agent
sudo apt install -y npm
npm install -g @anthropic-ai/claude-code
claude            # or his bypass alias on a disposable box only
```
Cloudflare side: records **Proxied (orange)**, SSL/TLS **Full (strict)** with an origin cert, **WAF** on,
**Turnstile** on forms, **"Under Attack"** during DDoS. Keep the **Hetzner panel firewall** as your
out-of-band rescue path.

---

## 6. Productization — market gap, comps, and product concepts

### The white space (this is the opportunity)
| Layer | What exists today | Price |
|---|---|---|
| Free literal-stack replications | `ashleyrudland/php-jquery-sqlite-starter-pack` (~44★, *"inspired by levelsio"*), `MarcoWorms/vibecoder-fullstack-vps-quickstart` (~101★, but React/Node not PHP) | Free, tiny, unpolished |
| Self-hosted PaaS | **Coolify** (v4, on Hetzner Marketplace), **Dokploy** (31k★) | Free OSS |
| AI vibe-coding platforms | **Cloudflare VibeSDK** (one-click your own platform), Lovable/Bolt/v0 | Free OSS / $10–39/mo |
| Secure-setup guides | Pere Villega "Running AI Coding Agents on Hetzner" (Tailscale + CF Tunnel), many Claude-on-VPS posts | Free content |
| **Paid boilerplate market** | **ShipFast $199** (7,394 buyers, $528K in 4 mo), **CodeFast $169** ($92K in 2 days), **SupaStarter $349** | **$119–$500 one-time** — but **100% React/Next/Supabase** |

**The gap:** nobody sells a *polished, paid* **levelsio-exact** kit (PHP+SQLite+jQuery+nginx + Cloudflare +
Tailscale-hardening + Claude-Code-on-VPS, one-click on Hetzner/DO). The free replications are small; the
paid market is the opposite stack; the PaaS and AI-platform layers are free OSS. Willingness-to-pay in
this niche is *proven* ($100K+ launches), the specific product just doesn't exist yet.

### Candidate products (low → high effort)
1. **Guidebook / paid course** — "The Vibe Server Handbook." Cheapest to ship, fastest to monetize. Comp:
   CodeFast $169 did $92K in 2 days. Risk: levelsio is publicly *anti-course* and the raw info is free on X.
   Differentiator must be the *polished, opinionated, security-correct, copy-paste* packaging he never gave.
2. **One-click marketplace image / install script** — a hardened Hetzner/DO Marketplace image (or a single
   `curl | bash` installer) that runs §5 + drops in the PHP/SQLite starter + installs Claude Code. Sell as a
   one-time license or freemium (free script, paid "pro" hardening + support). Comp: ShipFast $199.
3. **Opinionated starter repo** — the "ShipFast for the PHP/SQLite/jQuery/AI-VPS crowd": auth, Stripe, SES,
   R2, SQLite-with-WAL-and-backups baked in (closing his backup gap), a `CLAUDE.md` tuned for the stack
   (which he never published). Pairs naturally with #2.
4. **Managed provisioning SaaS** — a wizard: connect Hetzner/Cloudflare/Tailscale API keys → it provisions,
   hardens, installs the agent, and gives you a ready box + web terminal. Highest effort, recurring revenue,
   strongest moat. This is "Coolify, but opinionated around vibe-coding + security + the levelsio stack."

### The "are we just funneling costs?" test (read this before building anything)
The whole appeal of this stack is that it's **cheap and self-owned** — levelsio's entire brand is *"stop
paying for expensive VC-funded stuff, run a $5 box."* So any product that bolts a **recurring tax** onto it
is fighting the customer's reason for choosing it. Run each idea through three questions: *(a) Do the
underlying costs (Hetzner + Anthropic) exist whether or not we exist? (b) Are we creating net-new value or
just reselling/relaying spend? (c) Can it be copied to free?*

| Direction | Funnel exposure | Verdict |
|---|---|---|
| **Managed SaaS (#3)** | **Fatal.** A recurring fee to manage a $5 box that Coolify/Dokploy manage for free, stacked on top of the user's existing Hetzner+Anthropic bills. You become the expensive layer on the anti-expensive stack. | ❌ Drop it (as a consumer play) |
| **Installer / script (#1)** | Low — costs exist regardless; you sell time saved **once**. But trivially copied to free (MarcoWorms already is). | ⚠️ Thin moat, commodity |
| **Starter repo (#2)** | Low — sells IP once, like ShipFast. Moat is **your brand/maintenance**, not the code. Requires becoming a Marc-Lou-style personality. | ⚠️ Works but brand-dependent |
| **Course (#4)** | Low on funnel, but info is free on X and levelsio is anti-course. Moat = your teaching/brand. | ⚠️ Crowded |

**The uncomfortable conclusion:** selling *the setup* is structurally weak — it's either commodity (copied
to free) or brand-dependent (you have to become the personality). The stack is designed to be self-serve,
which is exactly why there's little durable margin in productizing the setup itself.

### Three ways to escape the funnel
1. **Don't sell the stack — *use* it.** levelsio doesn't sell his stack; he ships products on it (PhotoAI
   $132K/mo). His UptimeCanary replaced $64/mo UptimeRobot with a $5 box. The margin is in the **apps you
   build**, not the setup you teach. If the goal is revenue, the stack is the *means*, not the product.
2. **Sell the one non-commodity thing: trust/security.** The durable, hard-to-copy value is *"won't get
   cryptomined, the agent can't nuke prod, backups are real."* An **audited, hardened, backup-included**
   image with a security guarantee is defensible in a way a convenience script isn't. (Backups are NOT one of
   his gaps — he runs 3-2-1; the `IS_SANDBOX=1`-is-fake-isolation gap still stands.) This is the only "sell
   the setup" angle with a real moat.
3. **B2B / internal (best fit for an eng org like Cloverleaf).** Safe, isolated, **reproducible
   agent-ready cloud dev boxes** for a whole team — the real pain of "where do agents run that isn't my
   laptop and isn't prod." Here the funnel critique dissolves: the spend *is* the value (engineering safety
   + velocity), you're not reselling, and you're not betting on becoming an indie-hacker influencer.

### Hard truths to design around
- **Backups: don't claim he skips them (he doesn't).** He runs a real 3-2-1+ (Hetzner snapshots + R2 + B2 +
  Litestream). The product's value isn't *adding* backups — it's **wiring a sane default in automatically**
  so the buyer doesn't have to assemble four backup mechanisms by hand.
- **`IS_SANDBOX=1` is not real isolation** — sell **the disposable per-project VPS** (or real containers) as the safety story; don't repeat the cargo-cult.
- **He now endorses the full outbound tunnel** (May 2026) — inbound 100% blocked, web via Cloudflare
  tunnel + admin via Tailscale. A product should ship this as the default, not as a paid upgrade.
- **Attribution/brand risk** — leaning on the "levelsio" name to sell is legally/ethically fraught; position
  as "inspired by," credit him, don't imply endorsement.

---

## Appendix — key sources (with dates)
- Tailscale+Cloudflare lockdown (Mar 16 2026): x.com/levelsio/status/2033546675063554213 · follow-ups …2033547042396557421, …2033575565853180099
- "Never have password login" + checklist (Feb 2026): x.com/levelsio/status/2019064437248872647
- Permanent switch / bypass mode / 10x (early 2026): x.com/levelsio/status/2027566773814403448 · alias …2025962414085210559
- "HOW TO SECURE YOUR RAW DOG VPS" (Aug 2025): x.com/levelsio/status/1957526292045393976
- "HOW TO RAW DOG DEV ON THE SERVER" (Aug 2025): x.com/levelsio/status/1957518592284717558 · install recipe …1953022273595506910
- Vibeservering / Termius+Mosh / phone (Aug 2025): x.com/levelsio/status/1951957270989783501
- Claude on production / 3D computer (Aug 2025): x.com/levelsio/status/1951764854580105453 · "don't run bypass on prod" …1959012607270236619
- Simple stack ❤️ AI (Jul 2025): x.com/levelsio/status/1945125163793609032 · RemoteOK 27ms (Sep 2025) …1963408560420712671
- Full Cloudflare usage list (Apr 2024): x.com/levelsio/status/1775223692361871432
- Lex Fridman #440 transcript (Nov 2024, stack quotes; tooling outdated): lexfridman.com/pieter-levels-transcript/
- 2014 MVP blog (OUTDATED, historical only): levels.io/how-i-build-my-minimum-viable-products/
- Market comps: github.com/ashleyrudland/php-jquery-sqlite-starter-pack · github.com/MarcoWorms/vibecoder-fullstack-vps-quickstart · github.com/cloudflare/vibesdk · coolify.io · perevillega.com/posts/2026-04-02-running-ai-coding-agents-on-hetzner/ · shipfa.st

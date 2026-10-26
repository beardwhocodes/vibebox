# vibe base image (Packer)

Bakes a Hetzner snapshot with everything slow and secret-free pre-installed:
**Tailscale, cloudflared, Node 22 + Claude Code, Litestream, tmux, fail2ban,
unattended-upgrades, SSH hardening**. First boot is then near-instant.

## Important: how delivery actually works
Hetzner snapshots are **project-scoped — you cannot hand a buyer a ready snapshot.**
So the product ships *this Packer template*; the buyer runs it **in their own Hetzner
account** to bake their own image. That's the honest "few-click," not "one-click import."

## What this image deliberately does NOT do
No firewall lockdown, no Tailscale auth, no tunnel — all of those need the buyer's
**secrets** and must run **after first boot** so you don't bake a box that locks
everyone out. That step is [`../vibebox.sh`](../vibebox.sh), which locks down safely
(deny-all firewall only *after* Tailscale is verified, with a deadman rollback).

## Two-layer flow
```
1. packer build            -> your hardened "vibe-coding" snapshot (one time)
2. create server from it   -> Hetzner console / Terraform, with your SSH key
3. ssh in, run vibebox.sh  -> joins Tailscale, locks the firewall, sets up the tunnel
4. ssh <box> ; claude      -> code from laptop or phone
```

## Build it
```bash
export HCLOUD_TOKEN=your_hetzner_api_token
packer init .
packer validate vibe-base.pkr.hcl
packer build vibe-base.pkr.hcl
```
Outputs a snapshot named `vibe-coding-<os_image>-<version>-<timestamp>` (e.g. `vibe-coding-ubuntu-26.04-2026.05-…`) and a `manifest.json`. Override the OS with `-var "os_image=ubuntu-24.04"` if needed.

## Use it
Create a new server from the snapshot (console: *Add Server → Images → Snapshots*, or
Terraform `image = "<snapshot id>"`), **attach your SSH key**, then:
```bash
ssh root@NEW_SERVER_IP
curl -fsSL https://raw.githubusercontent.com/beardwhocodes/vibebox/main/vibebox.sh -o vibebox.sh
cp config.env.example config.env && nano config.env   # Tailscale key + CF token
bash vibebox.sh        # then verify Tailscale SSH and: bash vibebox.sh confirm
```

## Files
| File | Purpose |
|---|---|
| `vibe-base.pkr.hcl` | the build definition |
| `scripts/install-litestream.sh` | arch-aware latest Litestream |
| `scripts/tmux-auto.sh` | safe per-folder tmux helper (`vibe`) — no `cd` hijack |
| `scripts/finalize.sh` | shell aliases, enable services, validate sshd |
| `files/00-vibe-sshd.conf` | key-only SSH (named `00-` to beat cloud-init) |
| `files/20auto-upgrades.conf`, `files/51-auto-reboot.conf` | unattended upgrades + auto-reboot |

> Status: v0.2 — field-validated. `packer build` runs end-to-end in **~2m 41s** on a CX23, produces a working `vibe-coding-ubuntu-26.04-...` snapshot. `terraform apply` against the snapshot stands up a fully pre-baked box in **~40s** boot-to-ready (vs. ~3-5min on the stock-image path). Tested 2026-05-28 on EU-central.

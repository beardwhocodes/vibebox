// vibe-base.pkr.hcl — bakes a "vibe coding" Hetzner image with all the SLOW,
// secret-free software pre-installed. It deliberately does NOT touch the firewall,
// join Tailscale, or set up the tunnel — those need the buyer's secrets and must
// happen AFTER first boot (run vibebox.sh, which locks down safely with a deadman).
//
// Two-layer design:
//   1) this image  = fast first boot (apt installs already done)
//   2) vibebox.sh  = per-box lockdown with the buyer's Tailscale key + CF token
//
// Build (in YOUR OWN Hetzner account — snapshots can't be shared across accounts):
//   export HCLOUD_TOKEN=...   &&   packer init .   &&   packer build vibe-base.pkr.hcl

packer {
  required_plugins {
    hcloud = {
      version = "~> 1"
      source  = "github.com/hetznercloud/hcloud"
    }
  }
}

variable "hcloud_token" {
  type      = string
  sensitive = true
  default   = env("HCLOUD_TOKEN")
}

variable "image_version" {
  type    = string
  default = "2026.05"
}

variable "build_server_type" {
  type        = string
  default     = "cx23"
  description = "Build host SKU; final size is chosen at create time."
}

variable "location" {
  type        = string
  default     = "nbg1"
  description = "EU-central (cx23 only ships here); ash/hil for US (cpx11/cpx21)."
}

variable "os_image" {
  type        = string
  default     = "ubuntu-26.04"
  description = "Bump as new LTS releases (ubuntu-24.04 / 26.04 / ...)."
}

locals {
  full_name = "vibe-coding-${var.os_image}-${var.image_version}-${formatdate("YYYYMMDD-hhmm", timestamp())}"
}

source "hcloud" "vibe" {
  token        = var.hcloud_token
  image        = var.os_image
  location     = var.location
  server_type  = var.build_server_type
  ssh_username = "root"

  snapshot_name = local.full_name
  snapshot_labels = {
    name    = "vibe-coding"
    version = var.image_version
    purpose = "ai-vibe-coding"
  }
}

build {
  sources = ["source.hcloud.vibe"]

  // --- base packages -------------------------------------------------------
  provisioner "shell" {
    inline = [
      "export DEBIAN_FRONTEND=noninteractive",
      "apt-get update -qq && apt-get upgrade -y -qq",
      "apt-get install -y -qq curl wget git unzip tmux mosh htop jq ca-certificates gnupg ufw fail2ban unattended-upgrades",
    ]
  }

  // --- Tailscale (installed, NOT authed; user joins on first boot) ----------
  provisioner "shell" {
    inline = [
      "curl -fsSL https://tailscale.com/install.sh | sh",
      "systemctl enable tailscaled",
    ]
  }

  // --- Cloudflare Tunnel client (correct repo URL) -------------------------
  provisioner "shell" {
    inline = [
      "mkdir -p --mode=0755 /usr/share/keyrings",
      "curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null",
      "echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' > /etc/apt/sources.list.d/cloudflared.list",
      "apt-get update -qq && apt-get install -y -qq cloudflared",
    ]
  }

  // --- Node 22 + Claude Code ----------------------------------------------
  provisioner "shell" {
    inline = [
      "curl -fsSL https://deb.nodesource.com/setup_22.x | bash -",
      "apt-get install -y -qq nodejs",
      "npm install -g @anthropic-ai/claude-code",
    ]
  }

  // --- Litestream (arch-aware, latest release) -----------------------------
  provisioner "shell" {
    script = "scripts/install-litestream.sh"
  }

  // --- baked config files (safe, secret-free) ------------------------------
  provisioner "file" {
    source      = "files/00-vibe-sshd.conf"
    destination = "/etc/ssh/sshd_config.d/00-vibe.conf"
  }
  provisioner "file" {
    source      = "files/20auto-upgrades.conf"
    destination = "/etc/apt/apt.conf.d/20auto-upgrades"
  }
  provisioner "file" {
    source      = "files/51-auto-reboot.conf"
    destination = "/etc/apt/apt.conf.d/51-auto-reboot"
  }
  provisioner "file" {
    source      = "scripts/tmux-auto.sh"
    destination = "/etc/profile.d/tmux-auto.sh"
  }

  // --- finalize: shell tweaks, enable services, validate sshd (no restart) --
  provisioner "shell" {
    script = "scripts/finalize.sh"
  }

  // --- cleanup for a small, generic snapshot -------------------------------
  provisioner "shell" {
    inline = [
      "apt-get -y autoremove --purge -qq && apt-get -y clean",
      "cloud-init clean --logs --machine-id || true",
      "rm -rf /var/lib/cloud/instances/* /run/cloud-init/* /var/lib/apt/lists/* /tmp/* /var/tmp/* /root/.npm/_cacache || true",
      "truncate -s 0 /etc/machine-id || true",
      "fstrim --all || true",
      "sync",
    ]
  }

  post-processor "manifest" {
    output = "manifest.json"
  }
}

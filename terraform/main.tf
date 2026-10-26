// terraform/main.tf — provision a Hetzner server from your vibe-base snapshot.
// Pair with vibebox.sh: this creates the box; vibebox.sh locks it down post-boot.

terraform {
  required_version = ">= 1.6"
  required_providers {
    hcloud = {
      source  = "hetznercloud/hcloud"
      version = "~> 1.45"
    }
  }
}

provider "hcloud" {
  token = var.hcloud_token
}

// --- find the most recent snapshot whose description matches the prefix ----
// Built by packer with snapshot_name = "vibe-coding-<os_image>-<ver>-<ts>" (e.g. ubuntu-26.04).
data "hcloud_images" "vibe" {
  with_selector = "name=vibe-coding"
  most_recent   = true
}

// --- register the SSH public key we'll attach to the server ----------------
resource "hcloud_ssh_key" "default" {
  name       = "${var.project}-ssh"
  public_key = var.ssh_public_key
}

// Public SSH is needed only during bootstrap or an explicit recovery window.
// An empty rule set blocks new inbound connections and keeps outbound traffic.
resource "hcloud_firewall" "rescue" {
  name = "${var.project}-rescue"

  dynamic "rule" {
    for_each = var.public_ssh_enabled ? [1] : []
    content {
      direction  = "in"
      protocol   = "tcp"
      port       = "22"
      source_ips = [var.admin_ip_cidr]
    }
  }
  // outbound is open by default — we don't add egress rules
}

// --- the server ------------------------------------------------------------
resource "hcloud_server" "box" {
  name         = var.project
  image        = data.hcloud_images.vibe.images[0].id
  server_type  = var.server_type
  location     = var.location
  ssh_keys     = [hcloud_ssh_key.default.id]
  firewall_ids = [hcloud_firewall.rescue.id]

  // Preserve the live provider protection and reject accidental server replacement.
  delete_protection  = true
  rebuild_protection = true

  lifecycle {
    prevent_destroy = true
  }

  // Public networking stays on so you can SSH in the very first time and run
  // vibebox.sh. After lockdown, ufw closes everything inbound except Tailscale
  // and (optionally) the Cloudflare tunnel is outbound-only.
  public_net {
    ipv4_enabled = true
    ipv6_enabled = true
  }

  labels = {
    project = var.project
    managed = "terraform"
    kit     = "vibe"
  }
}

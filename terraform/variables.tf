variable "hcloud_token" {
  type        = string
  sensitive   = true
  description = "Hetzner Cloud API token (read+write on the project)."
}

variable "project" {
  type        = string
  description = "Short name used for the server, SSH key, and firewall. Becomes the hostname."
  default     = "vibebox-1"
}

variable "ssh_public_key" {
  type        = string
  description = "Your SSH public key content (the line from ~/.ssh/id_ed25519.pub)."
}

variable "admin_ip_cidr" {
  type        = string
  description = "Administrator CIDR allowed on public TCP 22 while public_ssh_enabled is true. Used for bootstrap or an explicit recovery window."
}

variable "public_ssh_enabled" {
  type        = bool
  default     = true
  description = "Allow public SSH from admin_ip_cidr for bootstrap or a recovery window. Set false after verifying Tailscale access."
}

variable "server_type" {
  type        = string
  description = "Hetzner server type. cx23=cheap EU dev box (~$5/mo, EU-central only), cpx11=US shared AMD, cx32=more headroom, cax11=ARM/budget."
  default     = "cx23"
}

variable "location" {
  type        = string
  description = "Hetzner datacenter: nbg1/fsn1 (EU-central; required for cx23), hel1 (EU north), ash/hil (US, use cpx11/cpx21 there)."
  default     = "nbg1"
}

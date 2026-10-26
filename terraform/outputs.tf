output "server_ip" {
  description = "Public IPv4 — use this once to SSH in and run vibebox.sh, then never again."
  value       = hcloud_server.box.ipv4_address
}

output "server_id" {
  description = "Hetzner server ID, useful for follow-up CLI calls."
  value       = hcloud_server.box.id
}

output "image_used" {
  description = "Snapshot id this server was built from (verifies you got the vibe base image)."
  value       = data.hcloud_images.vibe.images[0].id
}

output "next_step" {
  description = "What to run next."
  value       = "ssh root@${hcloud_server.box.ipv4_address}  # then: bash vibebox.sh"
}

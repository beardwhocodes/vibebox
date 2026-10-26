#!/usr/bin/env bash
# Final image tweaks: shell helpers, enable services, validate sshd. No restarts.
set -euo pipefail

chmod +x /etc/profile.d/tmux-auto.sh

grep -q 'tmux-auto.sh' /root/.bashrc 2>/dev/null \
  || echo 'source /etc/profile.d/tmux-auto.sh' >> /root/.bashrc

# Remove the legacy helper when a build starts from an older image.
sed -i '/^c() { IS_SANDBOX=1 claude --dangerously-skip-permissions "\$@"; }$/d' /root/.bashrc

systemctl enable tailscaled fail2ban unattended-upgrades >/dev/null 2>&1 || true

# Validate sshd config (the 00-vibe drop-in) WITHOUT restarting — the snapshot
# boots fresh, and key-only auth keeps working since servers launch with an SSH key.
sshd -t
echo "finalize OK"

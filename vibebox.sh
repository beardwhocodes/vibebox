#!/usr/bin/env bash
#
# vibebox.sh — one-command setup for a secure, agent-ready "vibe coding" VPS.
# Codifies the @levelsio stack (current as of May 2026):
#   Tailscale (SSH) + Cloudflare Tunnel (web) + inbound firewall 100% closed
#   + fail2ban + auto-upgrades + Claude Code on the box + optional PHP/SQLite app
#   + Litestream backups pre-wired (he runs a full 3-2-1; this just sets a sane default for you).
#
# Run as root on a FRESH Ubuntu 26.04 (or 24.04) box, right after you SSH in:
#   curl -fsSL https://raw.githubusercontent.com/beardwhocodes/vibebox/main/vibebox.sh -o vibebox.sh
#   # read it, then:
#   cp config.env.example config.env && nano config.env      # fill in your keys
#   bash vibebox.sh
#
# Safety: the firewall phase arms a deadman timer. If Tailscale doesn't actually
# work, the firewall auto-disables after ROLLBACK_SECONDS so you are never locked
# out. After you confirm you can still reach the box, run:  bash vibebox.sh confirm
#
# STATUS: v0.2 — field-validated on a fresh Hetzner CX23 (Ubuntu 26.04). 10 empirical
# bugs caught and fixed during validation; see GUIDE.md Appendix D for the full list.
# Read before running. Idempotent: safe to re-run.

set -euo pipefail

# Every phase that runs apt needs these. Setting once at the top covers single-phase
# re-runs ('bash vibebox.sh app_starter') that don't go through base_packages first.
export DEBIAN_FRONTEND=noninteractive
# Ubuntu 22.04+ runs `needrestart` as a dpkg post-install hook; it has its own
# interactive prompt that ignores DEBIAN_FRONTEND and hangs forever without a TTY.
# 'a' = automatically restart services; SUSPEND=1 short-circuits it entirely.
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1
# Wait up to 5 min for any concurrent unattended-upgrades / apt-daily run instead of
# erroring with "Could not get lock /var/lib/dpkg/lock-frontend."
APT_GET_OPTS=(-y -qq -o DPkg::Lock::Timeout=300)

# ---------------------------------------------------------------------------
# Config & defaults
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="${VIBEBOX_CONFIG:-$SCRIPT_DIR/config.env}"
STATE_DIR="/var/lib/vibebox"
ROLLBACK_UNIT="vibebox-firewall-rollback"

# Defaults (overridden by config.env)
TS_AUTHKEY="${TS_AUTHKEY:-}"            # Tailscale auth key (admin console > Settings > Keys)
TS_HOSTNAME="${TS_HOSTNAME:-$(hostname)}"
CF_TUNNEL_TOKEN="${CF_TUNNEL_TOKEN:-}" # Cloudflare > Zero Trust > Networks > Tunnels (token)
APP_DOMAIN="${APP_DOMAIN:-}"           # informational; the hostname you map in the CF dashboard
ADMIN_EMAIL="${ADMIN_EMAIL:-}"
SSH_PORT="${SSH_PORT:-22}"             # levelsio's optional "obscure port" flourish; 22 is fine
INSTALL_APP="${INSTALL_APP:-true}"     # drop in the PHP/SQLite/jQuery starter
INSTALL_CLAUDE="${INSTALL_CLAUDE:-true}"
INSTALL_BACKUPS="${INSTALL_BACKUPS:-true}"
ROLLBACK_SECONDS="${ROLLBACK_SECONDS:-600}"
APP_DIR="${APP_DIR:-/srv/app}"
APP_DATA_DIR="${APP_DATA_DIR:-/srv/data/vibebox}"
TAILSCALE_CGNAT="100.64.0.0/10"

# ---------------------------------------------------------------------------
# Pretty output
# ---------------------------------------------------------------------------
c_reset=$'\033[0m'; c_blue=$'\033[1;34m'; c_green=$'\033[1;32m'; c_yellow=$'\033[1;33m'; c_red=$'\033[1;31m'
log()  { printf '%s==>%s %s\n' "$c_blue"  "$c_reset" "$*"; }
ok()   { printf '%s  ✓%s %s\n' "$c_green" "$c_reset" "$*"; }
warn() { printf '%s  !%s %s\n' "$c_yellow" "$c_reset" "$*"; }
die()  { printf '%s  ✗%s %s\n' "$c_red" "$c_reset" "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Phase 00 — preflight
# ---------------------------------------------------------------------------
preflight() {
  log "Preflight checks"
  [ "$(id -u)" -eq 0 ] || die "Run as root (sudo bash vibebox.sh)."
  [ -r /etc/os-release ] || die "Cannot read /etc/os-release."
  . /etc/os-release
  [ "${ID:-}" = "ubuntu" ] || warn "Tested on Ubuntu; detected '${ID:-unknown}'. Proceeding anyway."
  ping -c1 -W3 1.1.1.1 >/dev/null 2>&1 || die "No internet connectivity."
  mkdir -p "$STATE_DIR"
  [ -f "$CONFIG_FILE" ] && { set -a; . "$CONFIG_FILE"; set +a; ok "Loaded $CONFIG_FILE"; } \
                        || warn "No config.env found — using env vars / defaults / prompts."
  ok "Preflight passed on ${PRETTY_NAME:-this host}"
}

# ---------------------------------------------------------------------------
# Phase 10 — base packages
# ---------------------------------------------------------------------------
base_packages() {
  log "Installing base packages"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update "${APT_GET_OPTS[@]}"
  apt-get install "${APT_GET_OPTS[@]}" curl ca-certificates gnupg ufw fail2ban unattended-upgrades \
                        jq git tmux mosh htop >/dev/null
  ok "Base packages installed"
}

# ---------------------------------------------------------------------------
# Phase 20 — SSH hardening (key-only, no passwords)
# ---------------------------------------------------------------------------
ssh_harden() {
  log "Hardening SSH (key-only, no password)"
  local d=/etc/ssh/sshd_config.d/00-vibebox.conf
  cat > "$d" <<EOF
# managed by vibebox
PasswordAuthentication no
PermitRootLogin prohibit-password
PubkeyAuthentication yes
KbdInteractiveAuthentication no
Port $SSH_PORT
EOF
  # Guard: refuse to disable passwords if root has no authorized key (would lock you out).
  if [ ! -s /root/.ssh/authorized_keys ] && [ -z "${ALLOW_NO_KEY:-}" ]; then
    rm -f "$d"
    die "No /root/.ssh/authorized_keys found — refusing to disable password auth (you'd be locked out).
     Add your public key first, or set ALLOW_NO_KEY=1 if you know what you're doing."
  fi
  sshd -t || die "sshd config test failed; not restarting."
  systemctl restart ssh 2>/dev/null || systemctl restart sshd
  ok "SSH hardened (port $SSH_PORT, key-only)"
}

# ---------------------------------------------------------------------------
# Phase 30 — Tailscale (private mesh for SSH); MUST succeed before firewall
# ---------------------------------------------------------------------------
tailscale_up() {
  log "Installing Tailscale"
  command -v tailscale >/dev/null 2>&1 || curl -fsSL https://tailscale.com/install.sh | sh
  if [ -n "$TS_AUTHKEY" ]; then
    tailscale up --ssh --hostname "$TS_HOSTNAME" --authkey "$TS_AUTHKEY"
  else
    warn "No TS_AUTHKEY set — starting interactive login. Open the URL it prints."
    tailscale up --ssh --hostname "$TS_HOSTNAME"
  fi
  local ts_ip; ts_ip="$(tailscale ip -4 2>/dev/null || true)"
  [ -n "$ts_ip" ] || die "Tailscale has no IPv4 yet — fix Tailscale before continuing (do NOT firewall)."
  echo "$ts_ip" > "$STATE_DIR/tailscale_ip"
  ok "Tailscale up — this box is reachable at $ts_ip and via 'ssh $TS_HOSTNAME'"
}

# ---------------------------------------------------------------------------
# Phase 40 — Firewall: deny all inbound, SSH only via Tailscale.
#            Web is served via the Cloudflare *outbound* tunnel, so NO public
#            web ports are opened (levelsio's current "inbound 100% closed").
#            Arms a deadman rollback so you cannot lock yourself out.
# ---------------------------------------------------------------------------
firewall() {
  log "Configuring firewall (deny inbound; SSH via Tailscale only)"
  [ -f "$STATE_DIR/tailscale_ip" ] || die "Run the tailscale phase first."

  # Deadman: auto-disable the firewall after ROLLBACK_SECONDS unless confirmed.
  cat > /usr/local/sbin/vibebox-rollback <<'EOF'
#!/usr/bin/env bash
ufw --force disable
logger -t vibebox "DEADMAN: firewall auto-disabled (no confirm received)."
EOF
  chmod +x /usr/local/sbin/vibebox-rollback
  systemctl stop "${ROLLBACK_UNIT}.timer" 2>/dev/null || true
  systemd-run --unit "$ROLLBACK_UNIT" --on-active="${ROLLBACK_SECONDS}s" \
              /usr/local/sbin/vibebox-rollback >/dev/null
  warn "Deadman armed: firewall auto-disables in $((ROLLBACK_SECONDS/60)) min unless you confirm."

  ufw --force reset >/dev/null
  ufw default deny incoming >/dev/null
  ufw default allow outgoing >/dev/null
  ufw allow from "$TAILSCALE_CGNAT" to any port "$SSH_PORT" proto tcp >/dev/null  # SSH via Tailscale
  ufw allow 41641/udp >/dev/null                                                  # Tailscale NAT traversal
  ufw --force enable >/dev/null
  ok "Firewall enabled. No public inbound ports. SSH only from Tailscale."

  cat <<EOF

  ${c_yellow}ACTION REQUIRED${c_reset} — verify you are NOT locked out:
    1. From your laptop (with Tailscale running): ssh $TS_HOSTNAME   (or ssh root@$(cat "$STATE_DIR/tailscale_ip"))
    2. Once that works, confirm so the deadman doesn't disable the firewall:
         ${c_green}bash vibebox.sh confirm${c_reset}
  If you can't reach it, do nothing — the firewall auto-disables in $((ROLLBACK_SECONDS/60)) min.

EOF
}

confirm() {
  systemctl stop "${ROLLBACK_UNIT}.timer" 2>/dev/null || true
  systemctl reset-failed "$ROLLBACK_UNIT" 2>/dev/null || true
  touch "$STATE_DIR/firewall_confirmed"
  ok "Confirmed. Deadman cancelled — firewall stays up."
}

# ---------------------------------------------------------------------------
# Phase 50 — Cloudflare Tunnel (outbound; serves web with no open inbound port)
# ---------------------------------------------------------------------------
cloudflared_tunnel() {
  log "Installing cloudflared binary"
  if ! command -v cloudflared >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    mkdir -p /usr/share/keyrings
    curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg \
      | tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
    echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main" \
      > /etc/apt/sources.list.d/cloudflared.list
    apt-get update "${APT_GET_OPTS[@]}" && apt-get install "${APT_GET_OPTS[@]}" cloudflared >/dev/null
  fi
  ok "cloudflared $(cloudflared --version 2>/dev/null | head -1 || echo installed)"

  if [ -z "$CF_TUNNEL_TOKEN" ]; then
    warn "No CF_TUNNEL_TOKEN — tunnel NOT configured. Binary is on the box, ready for later."
    warn "When ready: Cloudflare Zero Trust > Tunnels > create a tunnel, map your hostname to"
    warn "http://localhost:80, paste the token into config.env, then re-run:"
    warn "  bash vibebox.sh cloudflared_tunnel"
    return 0
  fi
  cloudflared service install "$CF_TUNNEL_TOKEN"
  systemctl enable --now cloudflared 2>/dev/null || true
  ok "Cloudflare Tunnel running (outbound). Map ${APP_DOMAIN:-your hostname} -> http://localhost:80 in the dashboard."
}

# ---------------------------------------------------------------------------
# Phase 60 — fail2ban
# ---------------------------------------------------------------------------
fail2ban_setup() {
  log "Configuring fail2ban"
  cat > /etc/fail2ban/jail.d/vibebox.conf <<EOF
[sshd]
enabled  = true
port     = $SSH_PORT
maxretry = 5
bantime  = 24h
EOF
  systemctl enable --now fail2ban >/dev/null 2>&1 || systemctl restart fail2ban
  ok "fail2ban active on SSH"
}

# ---------------------------------------------------------------------------
# Phase 70 — unattended upgrades + auto-reboot
# ---------------------------------------------------------------------------
auto_upgrades() {
  log "Enabling unattended security upgrades + auto-reboot"
  cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
  cat > /etc/apt/apt.conf.d/51vibebox-reboot <<'EOF'
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "04:00";
EOF
  systemctl enable --now unattended-upgrades >/dev/null 2>&1 || true
  ok "Auto security upgrades on; auto-reboot 04:00 if required"
}

# ---------------------------------------------------------------------------
# Phase 80 — Claude Code on the box
# ---------------------------------------------------------------------------
claude_code() {
  [ "$INSTALL_CLAUDE" = "true" ] || { warn "INSTALL_CLAUDE=false — skipping"; return 0; }
  log "Installing Node + Claude Code"
  if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null 2>&1
    apt-get install "${APT_GET_OPTS[@]}" nodejs >/dev/null
  fi
  npm install -g @anthropic-ai/claude-code >/dev/null 2>&1 || npm install -g @anthropic-ai/claude-code
  # Remove the helper from older installs. Permission bypass requires an explicit command.
  sed -i '/^c() { IS_SANDBOX=1 claude --dangerously-skip-permissions "\$@"; }$/d' /root/.bashrc
  ok "Claude Code installed. Run 'claude' with permission checks. Auth on first run."
}

# ---------------------------------------------------------------------------
# Phase 90 — App starter (nginx + PHP-FPM + SQLite, levelsio style)
# ---------------------------------------------------------------------------
app_starter() {
  [ "$INSTALL_APP" = "true" ] || { warn "INSTALL_APP=false — skipping"; return 0; }
  [[ "$APP_DIR" =~ ^/[a-zA-Z0-9_./-]*$ && "$APP_DATA_DIR" =~ ^/[a-zA-Z0-9_./-]*$ ]] \
    || die "App paths must be absolute and contain only letters, digits, slashes, dots, underscores, or hyphens."
  APP_DIR="$(realpath -m "$APP_DIR")"
  APP_DATA_DIR="$(realpath -m "$APP_DATA_DIR")"
  [ "$APP_DIR" != / ] && [ "$APP_DATA_DIR" != / ] || die "App paths must not be the filesystem root."
  case "$APP_DATA_DIR/" in "$APP_DIR/"*) die "APP_DATA_DIR must be outside APP_DIR." ;; esac
  # An existing application needs a deliberate migration, without a second empty database.
  [ ! -e "$APP_DIR/app.db" ] || die "Legacy app.db found in APP_DIR. Follow WEB-SECURITY.md before rerunning this phase."
  log "Installing nginx + PHP-FPM + SQLite starter"
  apt-get install "${APT_GET_OPTS[@]}" nginx php-fpm php-sqlite3 sqlite3 >/dev/null
  local php_sock; php_sock="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1)"
  [ -n "$php_sock" ] || die "PHP-FPM socket not found."
  mkdir -p "$APP_DIR"
  install -d -o www-data -g www-data -m 0750 "$APP_DATA_DIR"
  # Seed the starter from ./starter if present (when run from the repo), else inline minimal.
  if [ -d "$SCRIPT_DIR/starter" ]; then
    cp -rn "$SCRIPT_DIR/starter/." "$APP_DIR/" 2>/dev/null || true
  fi
  [ -f "$APP_DIR/index.php" ] || cat > "$APP_DIR/index.php" <<'PHP'
<?php // vibebox starter — single-file PHP + SQLite, no framework, no build step.
$db = new SQLite3(getenv('VIBEBOX_DB_PATH') ?: '/srv/data/vibebox/app.db'); $db->exec('PRAGMA journal_mode=WAL;');
$db->exec('CREATE TABLE IF NOT EXISTS visits(id INTEGER PRIMARY KEY, at TEXT)');
$db->exec("INSERT INTO visits(at) VALUES(datetime('now'))");
$n = $db->querySingle('SELECT COUNT(*) FROM visits');
echo "<!doctype html><meta charset=utf-8><title>vibebox</title>";
echo "<h1>It works 🚀</h1><p>Visit #$n. Edit ".__FILE__." and refresh.</p>";
PHP
  chown -R www-data:www-data "$APP_DIR"
  cat > /etc/nginx/sites-available/vibebox <<EOF
server {
    listen 127.0.0.1:80;          # localhost only — public access is via the Cloudflare tunnel
    server_name ${APP_DOMAIN:-_};
    root $APP_DIR; index index.php;
    # These rules precede PHP and static-file handling.
    location ~ (^|/)\. { return 404; }
    location ~* (?:\.(?:db|sqlite3?|sql)(?:[-.].*)?|\.(?:bak|backup|old|orig|save|swp|swo|tmp|log|ini|conf|ya?ml|toml|env)|~)$ { return 404; }
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param VIBEBOX_DB_PATH "$APP_DATA_DIR/app.db";
        fastcgi_pass unix:$php_sock;
    }
}
EOF
  ln -sf /etc/nginx/sites-available/vibebox /etc/nginx/sites-enabled/vibebox
  rm -f /etc/nginx/sites-enabled/default
  # Some Ubuntu installs leave a default-site server bound to 0.0.0.0:80 in the
  # running master, which makes our `listen 127.0.0.1:80` reload fail with
  # EADDRINUSE (systemctl reload still returns 0 — silent disaster). Force a
  # full restart on first setup so the old workers actually release the socket.
  nginx -t >/dev/null && systemctl restart nginx
  sleep 1
  systemctl is-active --quiet nginx || die "nginx failed to start — check 'journalctl -u nginx'"
  ok "App live at http://localhost:80 on the box (public via the tunnel). Code in $APP_DIR"
}

# ---------------------------------------------------------------------------
# Phase 95 — Litestream backups for SQLite (he runs 3-2-1; this pre-wires a sane default)
# ---------------------------------------------------------------------------
backups() {
  [ "$INSTALL_BACKUPS" = "true" ] || { warn "INSTALL_BACKUPS=false — skipping"; return 0; }
  log "Installing Litestream (SQLite streaming backups)"
  if ! command -v litestream >/dev/null 2>&1; then
    local arch lsarch ver url
    arch="$(dpkg --print-architecture)"
    # Litestream's .deb assets use x86_64 (not amd64); arm64/armv6/armv7 already match dpkg.
    case "$arch" in
      amd64) lsarch=x86_64 ;;
      *)     lsarch="$arch" ;;
    esac
    ver="$(curl -fsSL https://api.github.com/repos/benbjohnson/litestream/releases/latest \
            | grep -oP '"tag_name":\s*"\K[^"]+' || true)"
    [ -n "$ver" ] || { warn "Could not resolve latest Litestream version — skipping"; return 0; }
    # asset name uses the version WITHOUT the leading 'v' (e.g. litestream-0.5.11-linux-x86_64.deb)
    url="https://github.com/benbjohnson/litestream/releases/download/${ver}/litestream-${ver#v}-linux-${lsarch}.deb"
    if curl -fsSL -o /tmp/litestream.deb "$url"; then
      # dpkg -i can return non-zero on harmless post-install noise. Verify by PATH.
      dpkg -i /tmp/litestream.deb >/dev/null 2>&1 || true
      rm -f /tmp/litestream.deb
    else
      warn "Could not fetch $url — verify asset naming for ${ver}/${arch}."
    fi
  fi
  if command -v litestream >/dev/null 2>&1; then
    ok "Litestream $(litestream version 2>/dev/null || echo installed)"
  else
    warn "Litestream is NOT on PATH — install manually before relying on backups."
    return 0
  fi
  [ -f /etc/litestream.yml ] || cat > /etc/litestream.yml <<EOF
# Point this at your bucket (Cloudflare R2 / S3) and set creds, then:
#   systemctl enable --now litestream
dbs:
  - path: $APP_DATA_DIR/app.db
    replicas:
      - type: s3
        bucket: CHANGE_ME
        path: vibebox/app.db
        endpoint: CHANGE_ME           # e.g. https://<acct>.r2.cloudflarestorage.com
        # access-key-id / secret-access-key via env or here
EOF
  warn "Edit /etc/litestream.yml with your R2/S3 bucket, then: systemctl enable --now litestream"
}

# ---------------------------------------------------------------------------
# Orchestration
# ---------------------------------------------------------------------------
run_all() {
  preflight
  base_packages
  ssh_harden
  tailscale_up
  firewall          # arms deadman; prints the confirm instructions
  cloudflared_tunnel
  fail2ban_setup
  auto_upgrades
  claude_code
  app_starter
  backups
  echo
  ok "vibebox setup complete."
  warn "Don't forget: reconnect via Tailscale and run 'bash vibebox.sh confirm' to keep the firewall."
}

usage() {
  cat <<EOF
vibebox.sh — secure agent-ready VPS bootstrap

  bash vibebox.sh                 run everything
  bash vibebox.sh confirm         cancel the firewall deadman (run after verifying Tailscale SSH)
  bash vibebox.sh <phase>         run one phase

phases: preflight base_packages ssh_harden tailscale_up firewall cloudflared_tunnel
        fail2ban_setup auto_upgrades claude_code app_starter backups
EOF
}

main() {
  case "${1:-all}" in
    all|"")            run_all ;;
    confirm)           confirm ;;
    -h|--help|help)    usage ;;
    preflight|base_packages|ssh_harden|tailscale_up|firewall|cloudflared_tunnel|\
    fail2ban_setup|auto_upgrades|claude_code|app_starter|backups)
                       preflight; "$1" ;;
    *)                 usage; die "Unknown command: $1" ;;
  esac
}
main "${1:-all}"

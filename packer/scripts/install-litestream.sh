#!/usr/bin/env bash
# Install the latest Litestream .deb for this architecture.
set -euo pipefail
ARCH="$(dpkg --print-architecture)"               # amd64 | arm64
# Litestream uses x86_64 (not amd64) in linux .deb asset names; arm64 etc. already match.
case "$ARCH" in amd64) LSARCH=x86_64 ;; *) LSARCH="$ARCH" ;; esac
VER="$(curl -fsSL https://api.github.com/repos/benbjohnson/litestream/releases/latest \
        | grep -oP '"tag_name":\s*"\K[^"]+')"
# asset name strips the leading 'v' (e.g. litestream-0.5.11-linux-x86_64.deb)
URL="https://github.com/benbjohnson/litestream/releases/download/${VER}/litestream-${VER#v}-linux-${LSARCH}.deb"
echo "Installing Litestream ${VER} (${ARCH})"
if curl -fsSL -o /tmp/litestream.deb "$URL"; then
  dpkg -i /tmp/litestream.deb && rm -f /tmp/litestream.deb
  systemctl disable litestream 2>/dev/null || true   # off until the buyer adds a bucket
else
  echo "WARN: could not fetch $URL — verify the asset name for ${VER}/${ARCH}" >&2
fi

#!/usr/bin/env bash
# vibe tmux helper — per-project sessions. Sourced from /etc/profile.d.
# SAFE by design: acts only in interactive shells, never hijacks `cd`, never
# breaks scp/rsync/Ansible, never recurses inside an existing tmux session.

case $- in *i*) ;; *) return 0 2>/dev/null || exit 0 ;; esac   # interactive shells only
[ -n "${TMUX:-}" ] && return 0 2>/dev/null                     # already inside tmux

# `vibe`  -> attach (or create) a tmux session named after the current folder.
vibe() {
  local s; s="$(basename "$(pwd)" | tr -c 'A-Za-z0-9_-' '_')"
  tmux has-session -t "$s" 2>/dev/null || tmux new-session -d -s "$s"
  tmux attach -t "$s"
}

# Opt-in: uncomment to auto-attach a per-folder session on every interactive login.
# vibe

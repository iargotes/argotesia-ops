#!/usr/bin/env bash
set -euo pipefail

REMOTE="${REMOTE:-argotes-ops@46.202.179.60}"
REMOTE_DIR="${REMOTE_DIR:-/home/argotes-ops/htdocs/ops.argotes.com}"

rsync -az --delete \
  --exclude ".git/" \
  --exclude ".env" \
  --exclude "storage/logs/*" \
  --exclude "storage/cache/*" \
  ./ "$REMOTE:$REMOTE_DIR/"

ssh "$REMOTE" "cd '$REMOTE_DIR' && mkdir -p storage/logs storage/cache && chmod -R u+rwX storage"


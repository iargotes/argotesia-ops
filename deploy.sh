#!/usr/bin/env bash
set -euo pipefail

REMOTE="${REMOTE:-argotes-ops@46.202.179.60}"
REMOTE_APP_DIR="${REMOTE_APP_DIR:-/home/argotes-ops/app}"
REMOTE_WEB_DIR="${REMOTE_WEB_DIR:-/home/argotes-ops/htdocs/ops.argotes.com}"

rsync -az --delete \
  --exclude ".git/" \
  --exclude ".env" \
  --exclude ".env*.local" \
  --exclude "storage/logs/*" \
  --exclude "storage/cache/*" \
  ./ "$REMOTE:$REMOTE_APP_DIR/"

rsync -az --delete public/ "$REMOTE:$REMOTE_WEB_DIR/"

ssh "$REMOTE" "cd '$REMOTE_APP_DIR' && mkdir -p storage/logs storage/cache && chmod -R u+rwX storage && cat > '$REMOTE_WEB_DIR/index.php' <<'PHP'
<?php
declare(strict_types=1);

require '/home/argotes-ops/app/public/index.php';
PHP
cat > '$REMOTE_WEB_DIR/router.php' <<'PHP'
<?php
declare(strict_types=1);

require '/home/argotes-ops/app/public/router.php';
PHP
"

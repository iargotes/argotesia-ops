#!/bin/zsh
set -euo pipefail

export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
CONFIG_FILE="${OPS_AGENT_ENV_FILE:-$ROOT_DIR/.env.oscar.local}"
COMMAND="${1:-run}"

if [[ -f "$CONFIG_FILE" ]]; then
  set -a
  source "$CONFIG_FILE"
  set +a
fi

export OPS_BASE_URL="${OPS_BASE_URL:-https://ops.argotes.com}"
export OPS_WORKER_KEY="${OPS_WORKER_KEY:-oscar}"
export TELEGRAM_AGENT_URL="${TELEGRAM_AGENT_URL:-https://ainative.argotes.com}"
export LOCAL_MODEL_URL="${LOCAL_MODEL_URL:-}"
export LOCAL_MODEL_NAME="${LOCAL_MODEL_NAME:-local-template}"

if [[ "$OPS_WORKER_KEY" != "oscar" ]]; then
  print -u2 "OPS_WORKER_KEY must be oscar in $CONFIG_FILE"
  exit 1
fi

if [[ "$COMMAND" == "ask" ]]; then
  : "${TELEGRAM_AGENT_TOKEN:?TELEGRAM_AGENT_TOKEN is required in $CONFIG_FILE}"
else
  : "${OPS_WORKER_TOKEN:?OPS_WORKER_TOKEN is required in $CONFIG_FILE}"
fi

PHP_BIN="$(command -v php || true)"
if [[ -z "$PHP_BIN" ]]; then
  print -u2 "PHP CLI was not found. Install it with: brew install php"
  exit 1
fi

exec "$PHP_BIN" "$ROOT_DIR/scripts/mac-agent.php" "$@"

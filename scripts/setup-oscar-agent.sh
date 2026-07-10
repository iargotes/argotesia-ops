#!/bin/zsh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
CONFIG_FILE="$ROOT_DIR/.env.oscar.local"
EXAMPLE_FILE="$ROOT_DIR/.env.oscar.local.example"
RUNNER="$ROOT_DIR/scripts/ops-agent-oscar.sh"

if [[ ! -f "$CONFIG_FILE" ]]; then
  cp "$EXAMPLE_FILE" "$CONFIG_FILE"
  chmod 600 "$CONFIG_FILE"
  cat <<EOF
Created $CONFIG_FILE

Oscar must add these two values by private channel:
  OPS_WORKER_TOKEN=<Oscar's unique worker token>
  TELEGRAM_AGENT_TOKEN=<shared internal Telegram token>

OPS_API_TOKEN does not belong on Oscar's Mac.
After filling the file, run this command again.
EOF
  exit 2
fi

chmod 600 "$CONFIG_FILE"
set -a
source "$CONFIG_FILE"
set +a

: "${OPS_WORKER_TOKEN:?OPS_WORKER_TOKEN is required in $CONFIG_FILE}"
: "${TELEGRAM_AGENT_TOKEN:?TELEGRAM_AGENT_TOKEN is required in $CONFIG_FILE}"
if [[ "${OPS_WORKER_KEY:-}" != "oscar" ]]; then
  print -u2 "OPS_WORKER_KEY must be oscar in $CONFIG_FILE"
  exit 1
fi

command -v php >/dev/null 2>&1 || {
  print -u2 "PHP CLI is required. Install it with: brew install php"
  exit 1
}

response_file="$(mktemp)"
trap 'rm -f "$response_file"' EXIT
"$RUNNER" updates 0 > "$response_file"
php -r '
  $data = json_decode(file_get_contents($argv[1]), true);
  if (!is_array($data) || !($data["ok"] ?? false) || ($data["worker"] ?? "") !== "oscar") {
      fwrite(STDERR, "Oscar worker authentication failed.\n");
      exit(1);
  }
  echo "Oscar worker authenticated. Events: " . count($data["events"] ?? []) . "\n";
' "$response_file"

if [[ -n "${LOCAL_MODEL_URL:-}" ]]; then
  print "Local model configured: ${LOCAL_MODEL_NAME:-unnamed}"
else
  print "Local model not configured; run will use the safe proposal template."
fi

cat <<EOF

Configuration is valid.

Read assigned tickets without processing:
  ./scripts/ops-agent-oscar.sh tasks

Automatic local-model proposal:
  ./scripts/ops-agent-oscar.sh

Codex-assisted proposal:
  ./scripts/ops-agent-oscar.sh submit OPS-2026-00042 proposal.md

Internal question:
  ./scripts/ops-agent-oscar.sh ask OPS-2026-00042 "question"
EOF

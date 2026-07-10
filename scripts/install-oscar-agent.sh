#!/bin/zsh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
CONFIG_FILE="$ROOT_DIR/.env.oscar.local"
RUNNER="$ROOT_DIR/scripts/ops-agent-oscar.sh"
LABEL="com.argotesia.ops-agent.oscar"
PLIST="$HOME/Library/LaunchAgents/$LABEL.plist"
LOG_DIR="$HOME/Library/Logs/ArgotesIAOps"
MODE="${1:---install}"

command -v php >/dev/null 2>&1 || {
  print -u2 "PHP CLI is required. Install it with: brew install php"
  exit 1
}

render_plist() {
  local target="$1"
  local args_json
  args_json="$(php -r 'echo json_encode([$argv[1], "run"]);' "$RUNNER")"
  plutil -create xml1 "$target"
  plutil -insert Label -string "$LABEL" "$target"
  plutil -insert ProgramArguments -json "$args_json" "$target"
  plutil -insert WorkingDirectory -string "$ROOT_DIR" "$target"
  plutil -insert RunAtLoad -bool true "$target"
  plutil -insert StartInterval -integer 60 "$target"
  plutil -insert StandardOutPath -string "$LOG_DIR/oscar.out.log" "$target"
  plutil -insert StandardErrorPath -string "$LOG_DIR/oscar.err.log" "$target"
  plutil -lint "$target" >/dev/null
}

if [[ "$MODE" == "--render" ]]; then
  temp_plist="$(mktemp)"
  trap 'rm -f "$temp_plist"' EXIT
  render_plist "$temp_plist"
  cat "$temp_plist"
  exit 0
fi

if [[ "$MODE" != "--install" ]]; then
  print -u2 "Usage: ./scripts/install-oscar-agent.sh [--install|--render]"
  exit 1
fi

"$ROOT_DIR/scripts/setup-oscar-agent.sh"
set -a
source "$CONFIG_FILE"
set +a
if [[ -z "${LOCAL_MODEL_URL:-}" || -z "${LOCAL_MODEL_NAME:-}" || "$LOCAL_MODEL_NAME" == "local-template" ]]; then
  cat >&2 <<EOF
Background mode requires a real local model.
Set LOCAL_MODEL_URL and LOCAL_MODEL_NAME in $CONFIG_FILE first.
Codex-assisted mode remains manual and does not require LaunchAgent.
EOF
  exit 1
fi

mkdir -p "$HOME/Library/LaunchAgents" "$LOG_DIR"
temp_plist="$(mktemp)"
trap 'rm -f "$temp_plist"' EXIT
render_plist "$temp_plist"

if [[ -f "$PLIST" ]]; then
  cp "$PLIST" "$PLIST.bak.$(date +%Y%m%d%H%M%S)"
fi
launchctl bootout "gui/$(id -u)/$LABEL" >/dev/null 2>&1 || true
cp "$temp_plist" "$PLIST"
chmod 644 "$PLIST"
launchctl bootstrap "gui/$(id -u)" "$PLIST"
launchctl kickstart -k "gui/$(id -u)/$LABEL"

cat <<EOF
Oscar LaunchAgent installed.
Status: launchctl print gui/$(id -u)/$LABEL
Logs: $LOG_DIR
EOF

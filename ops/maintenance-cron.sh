#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_RETENTION_DAYS="${LOG_RETENTION_DAYS:-90}"
LOGROTATE_CONF="$PROJECT_DIR/ops/logrotate-dyndns.conf"
LOGROTATE_STATE="$PROJECT_DIR/var/log/logrotate.state"
ENV_FILE="${ENV_FILE:-$PROJECT_DIR/.env.local}"

cd "$PROJECT_DIR"
if [ ! -f "$ENV_FILE" ]; then
  echo "Env file not found: $ENV_FILE" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

if ! docker compose ps --status running --services | grep -qx 'dyndns'; then
  echo "Service 'dyndns' is not running; skipped DB log cleanup." >&2
  exit 1
fi

docker compose exec -T dyndns php bin/console app:cleanup-ddns-logs --days="$LOG_RETENTION_DAYS" --env=prod --no-interaction

if command -v logrotate >/dev/null 2>&1; then
  mkdir -p "$PROJECT_DIR/var/log"
  logrotate -s "$LOGROTATE_STATE" "$LOGROTATE_CONF"
else
  echo "logrotate is not installed; skipped file log rotation." >&2
fi

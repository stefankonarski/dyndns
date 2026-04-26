#!/usr/bin/env bash
set -euo pipefail

LOG_FILE="${1:-/var/log/nginx/access.log}"
SAMPLE_SIZE="${SAMPLE_SIZE:-5}"

if [ ! -f "$LOG_FILE" ]; then
  echo "Access log not found: $LOG_FILE" >&2
  exit 2
fi

if grep -nE "\"(GET|POST) /update\\?" "$LOG_FILE" | head -n "$SAMPLE_SIZE"; then
  echo "Query strings for /update were found in access logs." >&2
  exit 1
fi

echo "No query strings for /update found in $LOG_FILE."

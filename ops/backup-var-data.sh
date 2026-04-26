#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$PROJECT_DIR/var/data"
BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/var/backups/data}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_FILE="$BACKUP_DIR/data-$TIMESTAMP.tar.gz"

if [ ! -d "$SOURCE_DIR" ]; then
  echo "Source directory does not exist: $SOURCE_DIR" >&2
  exit 1
fi

mkdir -p "$BACKUP_DIR"
tar -C "$PROJECT_DIR/var" -czf "$BACKUP_FILE" data

find "$BACKUP_DIR" -type f -name 'data-*.tar.gz' -mtime "+$RETENTION_DAYS" -delete
echo "Backup created: $BACKUP_FILE"

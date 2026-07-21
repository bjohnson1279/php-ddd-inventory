#!/bin/bash
# Restore script for php-ddd-inventory database
if [ -z "$1" ]; then
  echo "Usage: $0 <path_to_backup_file.sql.gz>"
  exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
  echo "Error: Backup file '$BACKUP_FILE' not found."
  exit 1
fi

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

# Load environment variables from .env if present
if [ -f "$PROJECT_ROOT/.env" ]; then
  export $(grep -v '^#' "$PROJECT_ROOT/.env" | xargs)
fi

DB_USER=${DB_USERNAME:-ddd_user}
DB_NAME=${DB_DATABASE:-ddd_inventory}

echo "Starting PostgreSQL database restore from $BACKUP_FILE..."
gunzip -c "$BACKUP_FILE" | docker-compose -f "$PROJECT_ROOT/docker-compose.yml" exec -T db psql -U "$DB_USER" -d "$DB_NAME"

if [ ${PIPESTATUS[0]} -eq 0 ] && [ ${PIPESTATUS[1]} -eq 0 ]; then
  echo "Database successfully restored from $BACKUP_FILE"
else
  echo "Error: Database restore failed."
  exit 1
fi

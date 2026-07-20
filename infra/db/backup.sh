#!/bin/bash
# Backup script for php-ddd-inventory database
BACKUP_DIR="$(dirname "$0")/backups"
mkdir -p "$BACKUP_DIR"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/inventory_db_backup_$TIMESTAMP.sql.gz"

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

# Load environment variables from .env if present
if [ -f "$PROJECT_ROOT/.env" ]; then
  export $(grep -v '^#' "$PROJECT_ROOT/.env" | xargs)
fi

DB_USER=${DB_USERNAME:-ddd_user}
DB_NAME=${DB_DATABASE:-ddd_inventory}

echo "Starting PostgreSQL database backup..."
docker-compose -f "$PROJECT_ROOT/docker-compose.yml" exec -T db pg_dump -U "$DB_USER" -d "$DB_NAME" | gzip > "$BACKUP_FILE"

if [ ${PIPESTATUS[0]} -eq 0 ] && [ ${PIPESTATUS[1]} -eq 0 ]; then
  echo "Backup successfully created: $BACKUP_FILE"
else
  echo "Error: Database backup failed."
  exit 1
fi

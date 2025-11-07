#!/bin/bash
set -e

# Optional label parameter
LABEL=""
if [ -n "$1" ]; then
    LABEL="_$1"
fi

DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="./backups/${DATE}${LABEL}"
mkdir -p "$BACKUP_DIR"

echo "📦 Creating snapshot: ${DATE}${LABEL} ..."

# Dump the MariaDB database
echo "🗄️  Dumping phpBB database..."
docker exec phpbb-db mysqldump -u phpbbuser -pphpbbpass phpbb > "$BACKUP_DIR/phpbb.sql"

# Copy phpBB files (excluding cache)
echo "🧱 Backing up phpBB files..."
tar --exclude='phpbb/cache/*' -czf "$BACKUP_DIR/phpbb_files.tar.gz" ./phpbb

echo "✅ Snapshot created: $BACKUP_DIR"

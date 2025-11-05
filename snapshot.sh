#!/bin/bash
set -e

DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="./backups/$DATE"
mkdir -p "$BACKUP_DIR"

echo "📦 Creating snapshot at $BACKUP_DIR ..."

# Dump the MariaDB database
echo "🗄️  Dumping phpBB database..."
docker exec phpbb-db mysqldump -u phpbbuser -pphpbbpass phpbb > "$BACKUP_DIR/phpbb.sql"

# Copy phpBB files (excluding cache)
echo "🧱 Backing up phpBB files..."
tar --exclude='phpbb/cache/*' -czf "$BACKUP_DIR/phpbb_files.tar.gz" ./phpbb

echo "✅ Snapshot created: $BACKUP_DIR"

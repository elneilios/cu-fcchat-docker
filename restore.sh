#!/bin/bash
set -e

if [ -z "$1" ]; then
  echo "Usage: ./restore.sh <backup_folder>"
  exit 1
fi

BACKUP_DIR="./backups/$1"
if [ ! -d "$BACKUP_DIR" ]; then
  echo "❌ Backup folder not found: $BACKUP_DIR"
  exit 1
fi

echo "🧩 Restoring from $BACKUP_DIR ..."

# Stop containers
docker-compose down

# Restore phpBB files
echo "📂 Restoring phpBB files..."
tar -xzf "$BACKUP_DIR/phpbb_files.tar.gz" -C ./

# Restore database
echo "🗄️  Restoring phpBB database..."
docker-compose up -d db
sleep 10  # give MariaDB a few seconds to start
cat "$BACKUP_DIR/phpbb.sql" | docker exec -i phpbb-db mysql -u phpbbuser -pphpbbpass phpbb

# Bring the whole stack back up
docker-compose up -d

echo "✅ Restore complete!"

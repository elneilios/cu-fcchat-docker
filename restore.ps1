# restore.ps1 - Restore phpBB snapshot
param (
    [Parameter(Mandatory=$true)]
    [string]$SnapshotFolder
)

$backupDir = "C:\cu-fcchat-docker\backups\$SnapshotFolder"

if (!(Test-Path $backupDir)) {
    Write-Host "❌ Snapshot folder not found: $backupDir"
    exit
}

Write-Host "🔁 Restoring snapshot from $backupDir..."

# Stop running containers
docker-compose down

# Restore phpBB files
Write-Host "📁 Restoring phpBB files..."
Remove-Item -Recurse -Force "C:\cu-fcchat-docker\phpbb"
Copy-Item "$backupDir\phpbb_files" "C:\cu-fcchat-docker\phpbb" -Recurse -Force

# Copy database backup to db_init for container rebuilds
Write-Host "📋 Copying database backup to db_init..."
Copy-Item "$backupDir\phpbb_db.sql" "C:\cu-fcchat-docker\db_init\001_phpbb_backup.sql" -Force

# Restore database
Write-Host "🗃 Restoring database..."
docker-compose up -d db
Start-Sleep -Seconds 10
Get-Content "$backupDir\phpbb_db.sql" | docker exec -i phpbb-db sh -c "mysql -u phpbbuser -pphpbbpass phpbb"

# Bring everything up
docker-compose up -d

Write-Host "✅ Restore complete!"
Write-Host "ℹ️  Database backup also copied to db_init/ for container rebuilds"

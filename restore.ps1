param(
    [string] $SnapshotFolder
)

# restore.ps1 - Restore phpBB snapshot

$ErrorActionPreference = "Stop"

Write-Host "=== phpBB Snapshot Restore ===`n"

# List available backups
$backupsFolder = Join-Path $PSScriptRoot "backups"
if (-not (Test-Path $backupsFolder)) {
    Write-Host "❌ Backups folder not found: $backupsFolder" -ForegroundColor Red
    exit 1
}

# Get all backup folders
$backupFolders = @(Get-ChildItem -Path $backupsFolder -Directory | Select-Object -ExpandProperty Name | Sort-Object -Descending)
if ($backupFolders.Count -eq 0) {
    Write-Host "❌ No backup folders found in backups directory." -ForegroundColor Red
    exit 1
}

if ([string]::IsNullOrWhiteSpace($SnapshotFolder)) {
    Write-Host "Available snapshots:"
    for ($i = 0; $i -lt $backupFolders.Count; $i++) {
        Write-Host "[$($i+1)] $($backupFolders[$i])"
    }
    $selection = Read-Host "Select a snapshot by number (1-$($backupFolders.Count))"
    if ($selection -notmatch '^[1-9][0-9]*$' -or [int]$selection -lt 1 -or [int]$selection -gt $backupFolders.Count) {
        Write-Host "❌ Invalid selection." -ForegroundColor Red
        exit 1
    }
    $SnapshotFolder = $backupFolders[[int]$selection-1]
} else {
    if (-not ($backupFolders -contains $SnapshotFolder)) {
        Write-Host "❌ Snapshot folder not found: $SnapshotFolder" -ForegroundColor Red
        Write-Host "Available: $($backupFolders -join ', ')"
        exit 1
    }
}
$backupDir = Join-Path $backupsFolder $SnapshotFolder
Write-Host "Selected: $SnapshotFolder`n"


Write-Host "🔁 Restoring snapshot from $SnapshotFolder..."

# Stop running containers
docker-compose down

# Restore phpBB files
Write-Host "📁 Restoring phpBB files..."
$phpbbDir = Join-Path $PSScriptRoot "phpbb"
Remove-Item -Recurse -Force $phpbbDir -ErrorAction SilentlyContinue
Copy-Item "$backupDir\phpbb_files" $phpbbDir -Recurse -Force

# Copy database backup to db_init for container rebuilds
Write-Host "📋 Copying database backup to db_init..."
$dbInitFile = Join-Path $PSScriptRoot "db_init\001_phpbb_backup.sql"
Copy-Item "$backupDir\phpbb_db.sql" $dbInitFile -Force

# Restore database
Write-Host "🗃 Restoring database..."
docker-compose up -d db
Start-Sleep -Seconds 10
Get-Content "$backupDir\phpbb_db.sql" | docker exec -i phpbb-db sh -c "mysql -u phpbbuser -pphpbbpass phpbb"

# Sync custom styles
Write-Host "🎨 Syncing custom styles..."
& "$PSScriptRoot\sync-custom-styles.ps1"

# Bring everything up
docker-compose up -d

Write-Host "✅ Restore complete!"
Write-Host "ℹ️  Database backup also copied to db_init/ for container rebuilds"

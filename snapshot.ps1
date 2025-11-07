param(
    [string]$Label
)

# Enable UTF-8 output for proper icons
chcp 65001 | Out-Null
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# snapshot.ps1

$timestamp = Get-Date -Format "yyyyMMdd_HHmm"
$folderName = $timestamp
if (-not [string]::IsNullOrWhiteSpace($Label)) {
    $folderName = "${timestamp}_${Label}"
}
$backupDir = "C:\cu-fcchat-docker\backups\$folderName"

Write-Host ""
Write-Host "=== Creating snapshot: $folderName ==="
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

# --- Database backup ---
Write-Host "→ Backing up database..."
docker exec phpbb-db sh -c "mysqldump -u phpbbuser -pphpbbpass phpbb" | Out-File -FilePath "$backupDir\phpbb_db.sql" -Encoding utf8

# --- phpBB files backup ---
Write-Host "→ Backing up phpBB files..."
Copy-Item -Recurse -Force "C:\cu-fcchat-docker\phpbb" "$backupDir\phpbb_files"

Write-Host "=== Snapshot complete! ==="
Write-Host "Files saved to: $backupDir"

<#
.SYNOPSIS
    One-step phpBB upgrade helper for Docker containers
.DESCRIPTION
    - Backs up key phpBB files/folders
    - Uploads and extracts a phpBB update ZIP
    - Guides you through the manual database upgrade
    - Restores backed-up files and cleans up
    - Exits immediately if any step fails
#>

# Stop on first error
$ErrorActionPreference = "Stop"

Write-Host "=== phpBB Docker Upgrade Assistant ===`n"

# --- Ask for required info ---
# List zip files in updates folder and prompt for selection
$updatesFolder = Join-Path $PSScriptRoot "updates"
if (-not (Test-Path $updatesFolder)) {
  Write-Host "❌ Updates folder not found: $updatesFolder" -ForegroundColor Red
  exit 1
}

# Always treat $zipFiles as an array
$zipFiles = @(Get-ChildItem -Path $updatesFolder -Filter *.zip | Select-Object -ExpandProperty Name)
if ($zipFiles.Count -eq 0) {
  Write-Host "❌ No zip files found in updates folder." -ForegroundColor Red
  exit 1
}
Write-Host "Available update packages:"
for ($i = 0; $i -lt $zipFiles.Count; $i++) {
  Write-Host "[$($i+1)] $($zipFiles[$i])"
}
$selection = Read-Host "Select a package by number (1-$($zipFiles.Count))"
if ($selection -notmatch '^[1-9][0-9]*$' -or [int]$selection -lt 1 -or [int]$selection -gt $zipFiles.Count) {
  Write-Host "❌ Invalid selection." -ForegroundColor Red
  exit 1
}
$NewZipPath = Join-Path $updatesFolder $zipFiles[[int]$selection-1]
Write-Host "Selected: $NewZipPath"

$ContainerName = "phpbb"

# --- 1. Copy update ZIP into container ---
Write-Host "📦 Copying update package into container..."
docker cp "$NewZipPath" "${ContainerName}:/var/www/html/phpBB_update.zip"
Write-Host "✅ Successfully copied update package`n"

# --- 2. Back up key phpBB files ---
Write-Host "🗄️ Backing up config.php and key folders..."
docker exec -i $ContainerName bash -c '
set -e
cd /var/www/html
mkdir -p /var/www/html/phpBB_backup
cp config.php phpBB_backup/ 2>/dev/null || true
for d in files images store; do
  if [ -d "$d" ]; then cp -r "$d" phpBB_backup/; fi
done
echo "✅ Backup completed"
'

# --- 3. Extract the update ZIP ---
Write-Host "`n📂 Extracting update package on host..."
$tempDir = Join-Path $env:TEMP "phpbb_new"
if (Test-Path $tempDir) { Remove-Item -Recurse -Force $tempDir }
Expand-Archive -LiteralPath $NewZipPath -DestinationPath $tempDir -Force
docker exec -i $ContainerName bash -c "mkdir -p /var/www/html/phpBB_new"
docker cp "$tempDir/." "${ContainerName}:/var/www/html/phpBB_new/"
Remove-Item -Recurse -Force $tempDir
Write-Host "✅ Extracted and copied files into container"

# --- Verify extraction ---
$checkExtract = docker exec -i $ContainerName bash -c "[ -d /var/www/html/phpBB_new ] && echo OK"
if ($checkExtract -ne "OK") {
    Write-Host "❌ Extraction verification failed, aborting." -ForegroundColor Red
    exit 1
}


# --- 4. Copy new phpBB files into place ---
Write-Host "`n📁 Copying phpBB files into web root..."
docker exec -i $ContainerName bash -c '
set -e
if [ -d /var/www/html/phpBB_new/phpBB3 ]; then
  cd /var/www/html/phpBB_new/phpBB3
else
  cd /var/www/html/phpBB_new
fi
cp -r . /var/www/html/
echo "✅ File copy complete"
'

# --- Restore config.php before DB upgrade ---
Write-Host "`n🔄 Restoring backed-up config.php before database upgrade..."
docker exec -i $ContainerName bash -c '
set -e
cd /var/www/html
cp phpBB_backup/config.php .
'

# --- 5. Prompt for manual DB upgrade ---
Write-Host "`n======================================"
Write-Host "🧩 Step 1: Manual database upgrade"
Write-Host "In your browser, visit:"
Write-Host "   http://localhost:8080/install/database_update.php"
Write-Host "Run until you see 'Update completed', then return here."
Read-Host "Press Enter when done"

# --- 6. Post-upgrade tasks ---
Write-Host "`n🚀 Restoring backed-up folders and cleaning up..."
docker exec -i $ContainerName bash -c '
set -e
cd /var/www/html
for d in files images store; do
  if [ -d "phpBB_backup/$d" ]; then
    cp -r "phpBB_backup/$d" .
  fi
done
rm -rf phpBB_backup phpBB_new phpBB_update.zip install
echo "✅ Restore and cleanup complete"
'

# --- 7. Final message ---
Write-Host "`n🎉 Upgrade complete!"
Write-Host "You can now log in and verify your forum."

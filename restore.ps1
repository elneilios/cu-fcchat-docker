<#
.SYNOPSIS
    Restores a local phpBB Docker snapshot created by snapshot.ps1.

.DESCRIPTION
    Recreates the local Docker environment from a selected snapshot.

    The restore is intentionally destructive to LOCAL Docker state:
      - stops the Docker stack
      - removes the local named volumes
      - restores the phpBB working tree
      - restores the snapshot SQL dump as the database initialization dump
      - lets MySQL initialize the fresh database once
      - restores runtime data held in Docker volumes (files/ and store/)
      - leaves cache/ fresh

    Production is never contacted.

.PARAMETER SnapshotFolder
    Snapshot directory name under .\backups. If omitted, you will be prompted.

.PARAMETER SyncCustomStyles
    After restoring the exact snapshot, overwrite phpBB custom styles from
    .\custom-styles using sync-custom-styles.ps1.

.PARAMETER Yes
    Skip the interactive RESTORE confirmation.

.PARAMETER DryRun
    Validate the selected snapshot and show what would be done without changing
    the working tree, containers, volumes, or database.

.EXAMPLE
    .\restore.ps1

.EXAMPLE
    .\restore.ps1 -SnapshotFolder '20260809_010300_hardening_test'

.EXAMPLE
    .\restore.ps1 -SnapshotFolder '20260809_010300_hardening_test' -DryRun
#>

[CmdletBinding()]
param(
    [string] $SnapshotFolder,
    [switch] $SyncCustomStyles,
    [switch] $Yes,
    [switch] $DryRun
)

$ErrorActionPreference = 'Stop'

try {
    chcp 65001 | Out-Null
    [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
}
catch {}

function Invoke-Docker {
    param(
        [Parameter(Mandatory)]
        [string[]] $Arguments
    )

    & docker @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "Docker command failed (exit code $LASTEXITCODE): docker $($Arguments -join ' ')"
    }
}

function Invoke-Compose {
    param(
        [Parameter(Mandatory)]
        [string[]] $Arguments
    )

    & docker compose @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose command failed (exit code $LASTEXITCODE): docker compose $($Arguments -join ' ')"
    }
}

function Copy-DirectoryContents {
    param(
        [Parameter(Mandatory)]
        [string] $Source,

        [Parameter(Mandatory)]
        [string] $Destination
    )

    New-Item -ItemType Directory -Path $Destination -Force | Out-Null

    Get-ChildItem -LiteralPath $Source -Force | ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination $Destination -Recurse -Force
    }
}

function Wait-ForDatabaseInitialization {
    param(
        [int] $TimeoutMinutes = 15
    )

    $deadline = (Get-Date).AddMinutes($TimeoutMinutes)
    $sawInitComplete = $false

    while ((Get-Date) -lt $deadline) {
        $state = & docker inspect -f '{{.State.Status}}' phpbb-db 2>$null

        if ($LASTEXITCODE -eq 0 -and $state.Trim() -eq 'exited') {
            & docker logs phpbb-db
            throw 'MySQL container exited during initialization.'
        }

        $logs = (& docker logs phpbb-db 2>&1 | Out-String)

        if ($logs -match 'MySQL init process done\. Ready for start up\.') {
            $sawInitComplete = $true
        }

        if ($sawInitComplete) {
            & docker exec phpbb-db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -h 127.0.0.1 -uroot --silent' 2>$null | Out-Null

            if ($LASTEXITCODE -eq 0) {
                return
            }
        }

        Start-Sleep -Seconds 5
    }

    throw "Timed out after $TimeoutMinutes minutes waiting for MySQL initialization."
}

Write-Host ""
Write-Host "=== phpBB Local Snapshot Restore ===" -ForegroundColor Cyan
Write-Host ""

$backupsRoot = Join-Path $PSScriptRoot 'backups'

if (-not (Test-Path $backupsRoot -PathType Container)) {
    throw "Backups directory not found: $backupsRoot"
}

$availableSnapshots = @(
    Get-ChildItem -Path $backupsRoot -Directory |
    Sort-Object Name -Descending
)

if ($availableSnapshots.Count -eq 0) {
    throw 'No snapshots were found.'
}

if ([string]::IsNullOrWhiteSpace($SnapshotFolder)) {
    Write-Host 'Available snapshots:'

    for ($i = 0; $i -lt $availableSnapshots.Count; $i++) {
        Write-Host "[$($i + 1)] $($availableSnapshots[$i].Name)"
    }

    Write-Host ""
    $selection = Read-Host "Select a snapshot by number (1-$($availableSnapshots.Count))"

    if ($selection -notmatch '^\d+$') {
        throw 'Invalid snapshot selection.'
    }

    $selectionNumber = [int]$selection

    if ($selectionNumber -lt 1 -or $selectionNumber -gt $availableSnapshots.Count) {
        throw 'Invalid snapshot selection.'
    }

    $SnapshotFolder = $availableSnapshots[$selectionNumber - 1].Name
}

$backupDir = Join-Path $backupsRoot $SnapshotFolder

if (-not (Test-Path $backupDir -PathType Container)) {
    throw "Snapshot not found: $SnapshotFolder"
}

$dbSource    = Join-Path $backupDir 'phpbb_db.sql'
$filesSource = Join-Path $backupDir 'phpbb_files'

if (-not (Test-Path $dbSource -PathType Leaf)) {
    throw "Snapshot database dump is missing: $dbSource"
}

if ((Get-Item $dbSource).Length -eq 0) {
    throw "Snapshot database dump is empty: $dbSource"
}

if (-not (Test-Path $filesSource -PathType Container)) {
    throw "Snapshot phpBB files are missing: $filesSource"
}

foreach ($requiredFile in @('index.php', 'config.php')) {
    $requiredPath = Join-Path $filesSource $requiredFile

    if (-not (Test-Path $requiredPath -PathType Leaf)) {
        throw "Snapshot phpBB files do not contain required file: $requiredFile"
    }
}

$uploadsSource = Join-Path $filesSource 'files'
$storeSource   = Join-Path $filesSource 'store'

if (-not (Test-Path $uploadsSource -PathType Container)) {
    throw 'Snapshot does not contain the phpBB files/ upload directory.'
}

if (-not (Test-Path $storeSource -PathType Container)) {
    throw 'Snapshot does not contain the phpBB store/ directory.'
}

$dbSizeMB = [math]::Round((Get-Item $dbSource).Length / 1MB, 1)

Write-Host "Snapshot: $SnapshotFolder"
Write-Host "Database: $dbSizeMB MB"
Write-Host "Files:    $filesSource"

if ($SyncCustomStyles) {
    Write-Host "Styles:   snapshot will be restored, then repo custom-styles will be synced"
}
else {
    Write-Host "Styles:   exact snapshot contents will be retained"
}

Write-Host ""

if ($DryRun) {
    Write-Host "=== RESTORE DRY RUN PASSED ===" -ForegroundColor Green
    Write-Host "Would:"
    Write-Host "  1. Stop the LOCAL Docker stack and remove its named volumes"
    Write-Host "  2. Restore .\phpbb from the selected snapshot"
    Write-Host "  3. Replace db_init\001_phpbb_backup.sql with the snapshot database"
    Write-Host "  4. Start a fresh MySQL volume and wait for init scripts to finish"
    Write-Host "  5. Start phpBB and restore files/ + store/ into their named volumes"
    Write-Host "  6. Leave cache/ fresh"
    if ($SyncCustomStyles) {
        Write-Host "  7. Sync repo-managed custom styles"
    }
    Write-Host ""
    Write-Host "No files, containers, volumes, or databases were modified."
    exit 0
}

if (-not $Yes) {
    Write-Host "WARNING: This destroys and recreates the current LOCAL Docker database and named volumes." -ForegroundColor Yellow
    Write-Host "Production is not contacted."
    Write-Host ""
    $confirmation = Read-Host "Type exactly 'RESTORE' to continue"

    if ($confirmation -cne 'RESTORE') {
        Write-Host 'Restore cancelled.'
        exit 0
    }
}

$phpbbDir  = Join-Path $PSScriptRoot 'phpbb'
$dbInitDir = Join-Path $PSScriptRoot 'db_init'
$dbInitFile = Join-Path $dbInitDir '001_phpbb_backup.sql'

Write-Host ""
Write-Host "→ Stopping Docker and removing local named volumes..."
Invoke-Compose @('down', '-v')

Write-Host "→ Restoring phpBB working tree..."
if (Test-Path $phpbbDir) {
    Remove-Item -LiteralPath $phpbbDir -Recurse -Force
}

New-Item -ItemType Directory -Path $phpbbDir -Force | Out-Null
Copy-DirectoryContents -Source $filesSource -Destination $phpbbDir

if (Test-Path (Join-Path $phpbbDir 'phpbb_files')) {
    throw 'Restore safety check failed: nested phpbb\phpbb_files directory was created.'
}

Write-Host "✅ phpBB working tree restored"

Write-Host "→ Restoring database initialization dump..."
New-Item -ItemType Directory -Path $dbInitDir -Force | Out-Null
Copy-Item -LiteralPath $dbSource -Destination $dbInitFile -Force

if (-not (Test-Path $dbInitFile) -or (Get-Item $dbInitFile).Length -eq 0) {
    throw 'Database initialization dump could not be restored.'
}

Write-Host "✅ Database initialization dump restored"

Write-Host "→ Building phpBB image..."
Invoke-Compose @('build', 'phpbb')

Write-Host "→ Starting fresh MySQL database..."
Invoke-Compose @('up', '-d', 'db')

Write-Host "→ Waiting for MySQL initialization to complete..."
Wait-ForDatabaseInitialization
Write-Host "✅ MySQL initialization complete"

Write-Host "→ Starting phpBB..."
Invoke-Compose @('up', '-d', 'phpbb')

$deadline = (Get-Date).AddMinutes(2)
$phpbbRunning = $false

while ((Get-Date) -lt $deadline) {
    $running = & docker inspect -f '{{.State.Running}}' phpbb 2>$null

    if ($LASTEXITCODE -eq 0 -and $running.Trim() -eq 'true') {
        $phpbbRunning = $true
        break
    }

    Start-Sleep -Seconds 2
}

if (-not $phpbbRunning) {
    throw 'phpBB container did not start successfully.'
}

Write-Host "→ Restoring Docker-volume runtime data..."

# These paths are named Docker volumes and therefore hide the copies in the
# host phpbb tree once the container starts. Repopulate them explicitly.
& docker exec phpbb sh -c 'find /var/www/html/files -mindepth 1 -maxdepth 1 -exec rm -rf {} +'
if ($LASTEXITCODE -ne 0) {
    throw 'Could not clear the local files/ Docker volume before restoration.'
}

Invoke-Docker @('cp', "$uploadsSource\.", 'phpbb:/var/www/html/files/')

& docker exec phpbb sh -c 'find /var/www/html/store -mindepth 1 -maxdepth 1 -exec rm -rf {} +'
if ($LASTEXITCODE -ne 0) {
    throw 'Could not clear the local store/ Docker volume before restoration.'
}

Invoke-Docker @('cp', "$storeSource\.", 'phpbb:/var/www/html/store/')

# Cache is intentionally not restored. phpBB regenerates it and the entrypoint
# already clears it on startup.
& docker exec phpbb sh -c 'chown -R www-data:www-data /var/www/html/files /var/www/html/store && chmod -R 777 /var/www/html/files /var/www/html/store'
if ($LASTEXITCODE -ne 0) {
    throw 'Could not set phpBB runtime-directory permissions.'
}

if ($SyncCustomStyles) {
    Write-Host "→ Syncing repo-managed custom styles..."
    & (Join-Path $PSScriptRoot 'sync-custom-styles.ps1')

    if ($LASTEXITCODE -ne 0) {
        throw 'Custom style sync failed.'
    }
}

Write-Host "→ Verifying restored local environment..."

$codeVersion = (& docker exec phpbb php -r 'define("IN_PHPBB", true); $table_prefix = "phpbb_"; include "/var/www/html/includes/constants.php"; echo PHPBB_VERSION;' 2>$null | Out-String).Trim()

if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($codeVersion)) {
    throw 'Could not determine restored phpBB code version.'
}

Write-Host ""
Write-Host "=== Restore complete ===" -ForegroundColor Green
Write-Host "Snapshot:      $SnapshotFolder"
Write-Host "phpBB version: $codeVersion"
Write-Host "Forum:         http://localhost:8080"
Write-Host ""
Write-Host "Note: db_init\001_phpbb_backup.sql now contains this snapshot and will be used for future fresh DB-volume initialization."

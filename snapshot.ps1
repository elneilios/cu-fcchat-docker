<#
.SYNOPSIS
    Creates a complete snapshot of the running local phpBB Docker environment.

.DESCRIPTION
    Creates:
      - a full database dump
      - a copy of the effective /var/www/html tree from the running phpBB container

    The snapshot is saved under .\backups\<timestamp>_<label>.

    Database credentials are read from the running phpBB config.php rather than
    duplicated in this script.

.EXAMPLE
    .\snapshot.ps1 'before_extension_test'

.EXAMPLE
    .\snapshot.ps1
#>

[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [string] $Label
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

function Assert-ContainerRunning {
    param(
        [Parameter(Mandatory)]
        [string] $Name
    )

    $running = & docker inspect -f '{{.State.Running}}' $Name 2>$null

    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($running) -or $running.Trim() -ne 'true') {
        throw "Docker container '$Name' is not running."
    }
}

function ConvertTo-MyCnfValue {
    param(
        [AllowNull()]
        [string] $Value
    )

    if ($null -eq $Value) {
        $Value = ''
    }

    $escaped = $Value `
        -replace '\\', '\\\\' `
        -replace '"', '\"' `
        -replace "`r", '\r' `
        -replace "`n", '\n' `
        -replace "`t", '\t'

    return '"' + $escaped + '"'
}

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$folderName = $timestamp

if (-not [string]::IsNullOrWhiteSpace($Label)) {
    $safeLabel = $Label -replace '[^\w.-]', '_'
    $folderName = "${timestamp}_${safeLabel}"
}

$backupsRoot = Join-Path $PSScriptRoot 'backups'
$backupDir   = Join-Path $backupsRoot $folderName
$dbBackup    = Join-Path $backupDir 'phpbb_db.sql'
$filesBackup = Join-Path $backupDir 'phpbb_files'

$remoteDump = "/tmp/phpbb_snapshot_$timestamp.sql"
$remoteCnf  = "/tmp/phpbb_snapshot_$timestamp.cnf"
$localCnf   = Join-Path ([System.IO.Path]::GetTempPath()) "phpbb_snapshot_$timestamp.cnf"

Write-Host ""
Write-Host "=== Creating snapshot: $folderName ===" -ForegroundColor Cyan

Assert-ContainerRunning 'phpbb'
Assert-ContainerRunning 'phpbb-db'

if (Test-Path $backupDir) {
    throw "Snapshot directory already exists: $backupDir"
}

New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
New-Item -ItemType Directory -Force -Path $filesBackup | Out-Null

try {
    Write-Host "→ Reading database settings from phpBB config.php..."

    $phpCode = @'
include "/var/www/html/config.php";
echo json_encode([
    "host" => isset($dbhost) ? $dbhost : "",
    "port" => isset($dbport) ? $dbport : "",
    "name" => isset($dbname) ? $dbname : "",
    "user" => isset($dbuser) ? $dbuser : "",
    "pass" => isset($dbpasswd) ? $dbpasswd : ""
]);
'@

    $dbJson = & docker exec phpbb php -r $phpCode

    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($dbJson)) {
        throw 'Could not read database settings from phpBB config.php.'
    }

    try {
        $db = $dbJson | ConvertFrom-Json
    }
    catch {
        throw "phpBB database settings were not valid JSON: $dbJson"
    }

    if ([string]::IsNullOrWhiteSpace($db.name) -or [string]::IsNullOrWhiteSpace($db.user)) {
        throw 'phpBB config.php did not provide a database name/user.'
    }

    $dbHost = if ([string]::IsNullOrWhiteSpace($db.host)) { 'localhost' } else { [string]$db.host }

    $cnfLines = @(
        '[client]'
        "host=$(ConvertTo-MyCnfValue $dbHost)"
        "user=$(ConvertTo-MyCnfValue ([string]$db.user))"
        "password=$(ConvertTo-MyCnfValue ([string]$db.pass))"
    )

    if (-not [string]::IsNullOrWhiteSpace($db.port)) {
        $cnfLines += "port=$(ConvertTo-MyCnfValue ([string]$db.port))"
    }

    [System.IO.File]::WriteAllLines(
        $localCnf,
        $cnfLines,
        [System.Text.UTF8Encoding]::new($false)
    )

    Invoke-Docker @('cp', $localCnf, "phpbb:$remoteCnf")
    Invoke-Docker @('exec', 'phpbb', 'chmod', '600', $remoteCnf)

    Remove-Item $localCnf -Force -ErrorAction SilentlyContinue

    Write-Host "→ Backing up database..."

    $dumpCommand = @'
set -eu

CNF="$1"
DB_NAME="$2"
DUMP="$3"

if mysqldump --help 2>&1 | grep -q -- '--no-tablespaces'; then
    mysqldump --defaults-extra-file="$CNF" --no-tablespaces "$DB_NAME" > "$DUMP"
else
    mysqldump --defaults-extra-file="$CNF" "$DB_NAME" > "$DUMP"
fi

test -s "$DUMP"
'@

    & docker exec phpbb sh -c $dumpCommand sh $remoteCnf ([string]$db.name) $remoteDump

    if ($LASTEXITCODE -ne 0) {
        throw 'Database dump failed.'
    }

    Invoke-Docker @('cp', "phpbb:$remoteDump", $dbBackup)

    if (-not (Test-Path $dbBackup) -or (Get-Item $dbBackup).Length -eq 0) {
        throw 'Database dump is missing or empty.'
    }

    Write-Host "✅ Database backup complete"

    Write-Host "→ Backing up effective phpBB files..."

    Invoke-Docker @('cp', 'phpbb:/var/www/html/.', $filesBackup)

    $indexFile = Join-Path $filesBackup 'index.php'
    $configFile = Join-Path $filesBackup 'config.php'

    if (-not (Test-Path $indexFile)) {
        throw 'phpBB file backup does not contain index.php.'
    }

    if (-not (Test-Path $configFile)) {
        throw 'phpBB file backup does not contain config.php.'
    }

    Write-Host "✅ phpBB file backup complete"

    $dbSizeMB = [math]::Round((Get-Item $dbBackup).Length / 1MB, 1)

    Write-Host ""
    Write-Host "=== Snapshot complete ===" -ForegroundColor Green
    Write-Host "Location: $backupDir"
    Write-Host "Database: $dbSizeMB MB"
}
catch {
    Write-Host ""
    Write-Host "❌ Snapshot failed: $($_.Exception.Message)" -ForegroundColor Red

    if (Test-Path $backupDir) {
        Write-Host "Removing incomplete snapshot..."
        Remove-Item $backupDir -Recurse -Force -ErrorAction SilentlyContinue
    }

    throw
}
finally {
    Remove-Item $localCnf -Force -ErrorAction SilentlyContinue
    & docker exec phpbb rm -f $remoteDump $remoteCnf 2>$null | Out-Null
}

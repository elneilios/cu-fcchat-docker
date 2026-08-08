<#
.SYNOPSIS
    Unified phpBB 3.3.x point-upgrade tool for either local Docker or a remote VM.

.DESCRIPTION
    Runs the same target-side upgrade program in both environments. The PowerShell
    wrapper differs only in how it copies files to and executes commands on the target:
      - Docker: docker cp / docker exec
      - Remote: scp / ssh

    The target-side upgrade sequence is identical:
      1. Pre-flight validation (tools, PHP ZipArchive, phpBB structure, package/hash/version, DB access)
      2. Verify code and DB versions agree
      3. Optionally require phpBB maintenance mode (Remote actual runs by default)
      4. Create full target-side DB and filesystem rollback backups
      5. Follow phpBB's full-package core replacement pattern, retaining config.php/ext/files/images/store
      6. Prune retained data paths from, then install, the official phpBB FULL package
      7. Restore explicitly configured project files (robots.txt and styles/cu-fcchat by default)
      8. Run: php bin/phpbbcli.php db:migrate --safe-mode
      9. Verify both code and DB reached the package version
     10. Clear phpBB cache, remove install/, and retain the rollback backup

    If a post-backup upgrade step fails, rollback is attempted automatically unless
    -NoRollback is specified.

    This script deliberately supports phpBB 3.3.x -> 3.3.x point upgrades only.
    It is not intended for major-version migrations.

.NOTES
    Docker prerequisite:
      The phpBB web container must contain mysql/mysqldump clients because the same
      target-side script is used for Docker and Remote targets. Add `mariadb-client`
      to the Dockerfile apt packages, rebuild the image, then test locally first.

    Production prerequisite:
      Put the board into maintenance mode before an actual Remote upgrade. DryRun does
      not require maintenance mode so pre-flight checks can be performed beforehand.

.EXAMPLE
    .\upgrade-unified.ps1 -Target Docker -Package .\updates\phpBB-3.3.17.zip -ExpectedSourceVersion 3.3.15

.EXAMPLE
    .\upgrade-unified.ps1 -Target Remote -Package .\updates\phpBB-3.3.17.zip `
      -ExpectedSourceVersion 3.3.15 -ServerHost cu-fcchat.com `
      -KeyPath $HOME\.ssh\cu-fcchat-prod -DryRun

.EXAMPLE
    .\upgrade-unified.ps1 -Target Remote -Package .\updates\phpBB-3.3.17.zip `
      -ExpectedSourceVersion 3.3.15 -ServerHost cu-fcchat.com `
      -KeyPath $HOME\.ssh\cu-fcchat-prod
#>

[CmdletBinding()]
param(
    [ValidateSet('Docker', 'Remote')]
    [string] $Target = 'Docker',

    [string] $Package,
    [string] $ExpectedSourceVersion,

    # Docker target
    [string] $ContainerName = 'phpbb',

    # Remote target
    [string] $ServerHost,
    [string] $ServerUser = 'root',
    [int]    $ServerPort = 22,
    [string] $KeyPath,
    [string] $PhpbbPath = '/var/www/html',
    [switch] $NoHostKeyCheck,

    # Upgrade behaviour
    [string[]] $PreservePath = @('robots.txt', 'styles/cu-fcchat'),
    [switch] $DryRun,
    [switch] $AutoConfirm,
    [switch] $NoRollback,
    [switch] $KeepStaging,
    [string] $LogPath,
    [switch] $Help
)

$ErrorActionPreference = 'Stop'
$ScriptBuild = 'v6-portable-mysqldump'

try {
    chcp 65001 | Out-Null
    [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
} catch { }

function Write-Step {
    param([string]$Message, [ConsoleColor]$Color = [ConsoleColor]::Cyan)
    Write-Host $Message -ForegroundColor $Color
}

function Fail {
    param([string]$Message)
    throw $Message
}

function Get-SelectedPackage {
    param([string]$RequestedPackage)

    if (-not [string]::IsNullOrWhiteSpace($RequestedPackage)) {
        $resolved = Resolve-Path -LiteralPath $RequestedPackage -ErrorAction SilentlyContinue
        if (-not $resolved) { Fail "Update package not found: $RequestedPackage" }
        return $resolved.Path
    }

    $updatesFolder = Join-Path $PSScriptRoot 'updates'
    if (-not (Test-Path -LiteralPath $updatesFolder)) {
        Fail "Updates folder not found: $updatesFolder"
    }

    $zipFiles = @(Get-ChildItem -LiteralPath $updatesFolder -Filter '*.zip' -File | Sort-Object Name)
    if ($zipFiles.Count -eq 0) { Fail "No ZIP files found in: $updatesFolder" }

    Write-Host 'Available update packages:' -ForegroundColor Yellow
    for ($i = 0; $i -lt $zipFiles.Count; $i++) {
        Write-Host "[$($i + 1)] $($zipFiles[$i].Name)"
    }

    $selection = Read-Host "Select a package by number (1-$($zipFiles.Count))"
    if ($selection -notmatch '^\d+$' -or [int]$selection -lt 1 -or [int]$selection -gt $zipFiles.Count) {
        Fail 'Invalid package selection.'
    }

    return $zipFiles[[int]$selection - 1].FullName
}

function Get-PackageVersionFromName {
    param([string]$Path)
    $name = [System.IO.Path]::GetFileName($Path)
    if ($name -notmatch '^phpBB-(3\.3\.[0-9]+(?:-[A-Za-z0-9._-]+)?)\.zip$') {
        Fail "Package filename must look like phpBB-3.3.x.zip. Got: $name"
    }
    return $Matches[1]
}

function ConvertTo-PosixQuoted {
    param([AllowEmptyString()][string]$Value)
    # POSIX shell single-quote escaping:  abc'def -> 'abc'"'"'def'
    $sq = [string][char]39
    $dq = [string][char]34
    $replacement = $sq + $dq + $sq + $dq + $sq
    return $sq + $Value.Replace($sq, $replacement) + $sq
}

function Write-Utf8NoBomLf {
    param([string]$Path, [string]$Content)
    $lfContent = $Content -replace "`r`n", "`n"
    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $lfContent, $encoding)
}

if ($Help) {
    Get-Help $MyInvocation.MyCommand.Path -Detailed
    exit 0
}

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
if (-not $LogPath) {
    $logsFolder = Join-Path $PSScriptRoot 'logs'
    if (-not (Test-Path -LiteralPath $logsFolder)) {
        New-Item -ItemType Directory -Path $logsFolder -Force | Out-Null
    }
    $LogPath = Join-Path $logsFolder "upgrade_${Target}_$timestamp.log"
}

try { Start-Transcript -Path $LogPath -Force | Out-Null } catch { }

$localHelperScript = $null
$targetHelperScript = "/tmp/phpbb-upgrade-$timestamp.sh"
$targetPackage = "/tmp/phpbb-upgrade-$timestamp.zip"
$backupRoot = if ($Target -eq 'Remote') { '/root' } else { '/tmp' }
$expectedBackupDir = "$backupRoot/phpbb_upgrade_backup_$timestamp"

$sshArgs = @()
$scpArgs = @()
$serverConnection = $null
$targetConnected = $false

function Test-TargetConnection {
    if ($Target -eq 'Docker') {
        if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
            Fail 'docker is not available in PATH.'
        }
        $running = & docker inspect -f '{{.State.Running}}' $ContainerName 2>$null
        if ($LASTEXITCODE -ne 0 -or ($running | Out-String).Trim() -ne 'true') {
            Fail "Docker container '$ContainerName' is not running."
        }
        return
    }

    if ([string]::IsNullOrWhiteSpace($ServerHost)) {
        Fail '-ServerHost is required for Target Remote.'
    }
    if (-not (Get-Command ssh -ErrorAction SilentlyContinue)) { Fail 'ssh is not available in PATH.' }
    if (-not (Get-Command scp -ErrorAction SilentlyContinue)) { Fail 'scp is not available in PATH.' }

    & ssh @sshArgs $serverConnection 'echo ok' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Fail "SSH connection failed for $serverConnection on port $ServerPort."
    }
}

function Copy-ToTarget {
    param([string]$LocalPath, [string]$TargetPath)

    if ($Target -eq 'Docker') {
        & docker cp $LocalPath "${ContainerName}:$TargetPath"
        if ($LASTEXITCODE -ne 0) { Fail "docker cp failed: $LocalPath -> $TargetPath" }
        return
    }

    & scp @scpArgs $LocalPath "$serverConnection`:$TargetPath"
    if ($LASTEXITCODE -ne 0) { Fail "scp failed: $LocalPath -> $TargetPath" }
}

function Invoke-TargetArgs {
    param([string[]]$ArgumentList)

    # Native command stdout must be sent to the host rather than PowerShell's
    # success pipeline. Otherwise assigning this function's result also captures
    # every line of target output along with the numeric exit code.
    if ($Target -eq 'Docker') {
        & docker exec -i $ContainerName @ArgumentList | Out-Host
        $nativeExitCode = $LASTEXITCODE
        return [int]$nativeExitCode
    }

    $remoteCommand = ($ArgumentList | ForEach-Object { ConvertTo-PosixQuoted $_ }) -join ' '
    & ssh @sshArgs $serverConnection $remoteCommand | Out-Host
    $nativeExitCode = $LASTEXITCODE
    return [int]$nativeExitCode
}

function Remove-TargetTempFiles {
    $cmd = "rm -f $(ConvertTo-PosixQuoted $targetHelperScript) $(ConvertTo-PosixQuoted $targetPackage)"
    if ($Target -eq 'Docker') {
        & docker exec -i $ContainerName bash -lc $cmd 2>$null | Out-Null
    } else {
        & ssh @sshArgs $serverConnection $cmd 2>$null | Out-Null
    }
}

# Build connection arguments before the first connectivity test.
if ($Target -eq 'Remote') {
    $serverConnection = "$ServerUser@$ServerHost"
    if ($ServerPort) {
        $sshArgs += @('-p', $ServerPort)
        $scpArgs += @('-P', $ServerPort)
    }
    if ($KeyPath) {
        $sshArgs += @('-i', $KeyPath)
        $scpArgs += @('-i', $KeyPath)
    }
    if ($NoHostKeyCheck) {
        $hostOptions = @('-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null')
        $sshArgs += $hostOptions
        $scpArgs += $hostOptions
    }
}

$targetProgram = @'
#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

PHPBB_PATH=""
PACKAGE=""
TARGET_VERSION=""
PACKAGE_SHA=""
BACKUP_ROOT=""
RUN_ID=""
EXPECTED_SOURCE_VERSION=""
DRY_RUN=0
NO_ROLLBACK=0
KEEP_STAGING=0
REQUIRE_MAINTENANCE=0
PRESERVE_PATHS=()
PRESERVE_PATH_COUNT=0

while [ "$#" -gt 0 ]; do
    case "$1" in
        --phpbb-path) PHPBB_PATH="$2"; shift 2 ;;
        --package) PACKAGE="$2"; shift 2 ;;
        --target-version) TARGET_VERSION="$2"; shift 2 ;;
        --package-sha) PACKAGE_SHA="$2"; shift 2 ;;
        --backup-root) BACKUP_ROOT="$2"; shift 2 ;;
        --run-id) RUN_ID="$2"; shift 2 ;;
        --expected-source-version) EXPECTED_SOURCE_VERSION="$2"; shift 2 ;;
        --preserve-path) PRESERVE_PATHS[PRESERVE_PATH_COUNT]="$2"; PRESERVE_PATH_COUNT=$((PRESERVE_PATH_COUNT + 1)); shift 2 ;;
        --dry-run) DRY_RUN=1; shift ;;
        --no-rollback) NO_ROLLBACK=1; shift ;;
        --keep-staging) KEEP_STAGING=1; shift ;;
        --require-maintenance) REQUIRE_MAINTENANCE=1; shift ;;
        *) echo "ERROR: Unknown target argument: $1" >&2; exit 2 ;;
    esac
done

STAGING="/tmp/phpbb-upgrade-${RUN_ID}"
EXTRACT_DIR="$STAGING/extracted"
PRESERVE_DIR="$STAGING/preserve"
MYSQL_CNF="$STAGING/mysql.cnf"
BACKUP_DIR="$BACKUP_ROOT/phpbb_upgrade_backup_${RUN_ID}"
BACKUP_COMPLETE=0
MODIFIED=0
ROLLBACK_ATTEMPTED=0

say() { printf '%s\n' "$*"; }
fail() { say "ERROR: $*" >&2; return 1; }

cleanup_staging() {
    rm -f "$MYSQL_CNF" "$STAGING/db.env" "$STAGING/drop_objects.sql" 2>/dev/null || true
    if [ "$KEEP_STAGING" -eq 0 ]; then
        rm -rf "$STAGING" 2>/dev/null || true
    else
        say "Staging retained: $STAGING"
    fi
}

drop_database_objects() {
    local sql_file="$STAGING/drop_objects.sql"
    : > "$sql_file"
    printf 'SET FOREIGN_KEY_CHECKS=0;\n' >> "$sql_file"
    mysql --defaults-extra-file="$MYSQL_CNF" -N -B "$DBNAME" -e \
      "SELECT CONCAT('DROP VIEW IF EXISTS ', CHAR(96), REPLACE(table_name, CHAR(96), CONCAT(CHAR(96), CHAR(96))), CHAR(96), ';') FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'VIEW';" \
      >> "$sql_file" || return $?
    mysql --defaults-extra-file="$MYSQL_CNF" -N -B "$DBNAME" -e \
      "SELECT CONCAT('DROP TABLE IF EXISTS ', CHAR(96), REPLACE(table_name, CHAR(96), CONCAT(CHAR(96), CHAR(96))), CHAR(96), ';') FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE';" \
      >> "$sql_file" || return $?
    printf 'SET FOREIGN_KEY_CHECKS=1;\n' >> "$sql_file"
    mysql --defaults-extra-file="$MYSQL_CNF" "$DBNAME" < "$sql_file" || return $?
    return 0
}

rollback() {
    ROLLBACK_ATTEMPTED=1
    trap - ERR EXIT
    set +e

    say ""
    say "*** UPGRADE FAILED - ATTEMPTING ROLLBACK ***"
    say "Backup: $BACKUP_DIR"

    if [ -f "$BACKUP_DIR/phpbb_files_backup.tgz" ]; then
        say "Restoring phpBB files..."
        # Keep phpBB data directories/mount points in place so this same rollback works
        # in Docker (nested named volumes) and on the VM. Remove all replaceable core
        # entries, clear transient cache contents, then overlay the full pre-upgrade tar.
        find "$PHPBB_PATH" -mindepth 1 -maxdepth 1 \
            ! -name 'config.php' \
            ! -name 'ext' \
            ! -name 'files' \
            ! -name 'images' \
            ! -name 'store' \
            ! -name 'cache' \
            -exec rm -rf -- {} +
        if [ -d "$PHPBB_PATH/cache" ]; then
            find "$PHPBB_PATH/cache" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
        fi
        tar -C "$(dirname "$PHPBB_PATH")" -xzpf "$BACKUP_DIR/phpbb_files_backup.tgz"
        FILE_RC=$?
    else
        say "WARNING: File backup missing; cannot restore files." >&2
        FILE_RC=1
    fi

    if [ -f "$BACKUP_DIR/phpbb_db_backup.sql" ] && [ -f "$MYSQL_CNF" ]; then
        say "Restoring database..."
        drop_database_objects
        DROP_RC=$?
        if [ "$DROP_RC" -eq 0 ]; then
            mysql --defaults-extra-file="$MYSQL_CNF" "$DBNAME" < "$BACKUP_DIR/phpbb_db_backup.sql"
            DB_RC=$?
        else
            DB_RC=$DROP_RC
        fi
    else
        say "WARNING: Database backup or MySQL credentials file missing; cannot restore database." >&2
        DB_RC=1
    fi


    if [ "$FILE_RC" -eq 0 ] && [ "$DB_RC" -eq 0 ]; then
        say "Rollback completed successfully."
    else
        say "CRITICAL: Rollback was incomplete. Keep the board offline and restore manually from: $BACKUP_DIR" >&2
    fi

    cleanup_staging
}

on_exit() {
    rc=$?
    trap - EXIT
    if [ "$rc" -ne 0 ] && [ "$MODIFIED" -eq 1 ] && [ "$BACKUP_COMPLETE" -eq 1 ] && [ "$NO_ROLLBACK" -eq 0 ] && [ "$ROLLBACK_ATTEMPTED" -eq 0 ]; then
        rollback
    else
        cleanup_staging
    fi
    exit "$rc"
}
trap on_exit EXIT

for required in PHPBB_PATH PACKAGE TARGET_VERSION PACKAGE_SHA BACKUP_ROOT RUN_ID; do
    value="${!required}"
    [ -n "$value" ] || fail "Missing required value: $required"
done

case "$PHPBB_PATH" in
    /|/var|/var/www|/home|/root|/tmp) fail "Refusing unsafe phpBB path: $PHPBB_PATH" ;;
esac

[ -d "$PHPBB_PATH" ] || fail "phpBB path does not exist: $PHPBB_PATH"
[ -f "$PHPBB_PATH/config.php" ] || fail "config.php not found under: $PHPBB_PATH"
[ -f "$PHPBB_PATH/index.php" ] || fail "index.php not found under: $PHPBB_PATH"
[ -f "$PHPBB_PATH/includes/constants.php" ] || fail "includes/constants.php not found under: $PHPBB_PATH"
[ -f "$PACKAGE" ] || fail "Update package not found on target: $PACKAGE"
[ -d "$BACKUP_ROOT" ] || fail "Backup root does not exist: $BACKUP_ROOT"
[ -w "$BACKUP_ROOT" ] || fail "Backup root is not writable: $BACKUP_ROOT"

say "=== Target pre-flight ==="
for tool in bash php tar mysql mysqldump sha256sum cp find grep sed awk du df stat; do
    command -v "$tool" >/dev/null 2>&1 || fail "Required target tool is missing: $tool"
done
say "Required tools: OK"

# Use PHP's ZipArchive instead of requiring an OS-level unzip utility. phpBB already
# requires PHP, and this keeps Docker/VM extraction behaviour identical.
php -r 'exit(class_exists("ZipArchive") ? 0 : 1);' \
    || fail 'PHP ZipArchive is unavailable. The PHP zip extension is required.'
say "PHP ZipArchive: OK"

mkdir -p "$STAGING" "$EXTRACT_DIR" "$PRESERVE_DIR"
chmod 700 "$STAGING"

ACTUAL_SHA="$(sha256sum "$PACKAGE" | awk '{print $1}')"
[ "$ACTUAL_SHA" = "$PACKAGE_SHA" ] || fail "Package SHA-256 mismatch. Expected $PACKAGE_SHA, got $ACTUAL_SHA"
say "Package SHA-256: OK"

# Read phpBB DB settings on the target without exposing the password in process arguments or logs.
php -r '
include $argv[1];
$values = array(
  "DBHOST" => (!empty($dbhost) ? $dbhost : "localhost"),
  "DBPORT" => (!empty($dbport) ? $dbport : "3306"),
  "DBNAME" => isset($dbname) ? $dbname : "",
  "DBUSER" => isset($dbuser) ? $dbuser : "",
  "DBPASS" => isset($dbpasswd) ? $dbpasswd : "",
  "TABLE_PREFIX" => isset($table_prefix) ? $table_prefix : "phpbb_"
);
foreach ($values as $k => $v) {
  echo $k . "=" . escapeshellarg((string) $v) . PHP_EOL;
}
' "$PHPBB_PATH/config.php" > "$STAGING/db.env"
# shellcheck disable=SC1090
. "$STAGING/db.env"
rm -f "$STAGING/db.env"

[ -n "$DBNAME" ] || fail "Database name is empty in config.php"
[ -n "$DBUSER" ] || fail "Database user is empty in config.php"
case "$TABLE_PREFIX" in
    *[!A-Za-z0-9_]*) fail "Unsafe table prefix in config.php: $TABLE_PREFIX" ;;
esac

mysql_cnf_escape() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'
}
{
    printf '[client]\n'
    printf 'host="%s"\n' "$(mysql_cnf_escape "$DBHOST")"
    printf 'port="%s"\n' "$(mysql_cnf_escape "$DBPORT")"
    printf 'user="%s"\n' "$(mysql_cnf_escape "$DBUSER")"
    printf 'password="%s"\n' "$(mysql_cnf_escape "$DBPASS")"
} > "$MYSQL_CNF"
chmod 600 "$MYSQL_CNF"

mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e 'SELECT 1' "$DBNAME" >/dev/null
say "Database connectivity: OK ($DBNAME on $DBHOST)"

# Read-only dump probe: catches client/server incompatibility before any application changes.
# Avoid expanding an empty Bash array here: older Bash versions (including
# CentOS 7's Bash 4.2) can treat an empty-array expansion under `set -u` as
# an unbound variable. Docker's
# newer Bash did not expose this because its MariaDB client supports
# --no-tablespaces, so the old array was non-empty there.
DUMP_NO_TABLESPACES=0
if mysqldump --help 2>&1 | grep -q -- '--no-tablespaces'; then
    DUMP_NO_TABLESPACES=1
fi

run_mysqldump() {
    if [ "$DUMP_NO_TABLESPACES" -eq 1 ]; then
        mysqldump --defaults-extra-file="$MYSQL_CNF" --no-tablespaces "$@"
    else
        mysqldump --defaults-extra-file="$MYSQL_CNF" "$@"
    fi
}

run_mysqldump --no-data --single-transaction --quick "$DBNAME" >/dev/null
if [ "$DUMP_NO_TABLESPACES" -eq 1 ]; then
    say "Database dump probe: OK (--no-tablespaces supported)"
else
    say "Database dump probe: OK (--no-tablespaces not supported; standard options used)"
fi

get_code_version() {
    php -r 'error_reporting(0); define("IN_PHPBB", true); $table_prefix = ""; include $argv[1]; if (!defined("PHPBB_VERSION")) { exit(2); } echo PHPBB_VERSION;' "$1/includes/constants.php"
}

get_db_version() {
    mysql --defaults-extra-file="$MYSQL_CNF" -N -B "$DBNAME" -e "SELECT config_value FROM \`${TABLE_PREFIX}config\` WHERE config_name='version' LIMIT 1;"
}

get_board_disabled() {
    mysql --defaults-extra-file="$MYSQL_CNF" -N -B "$DBNAME" -e "SELECT config_value FROM \`${TABLE_PREFIX}config\` WHERE config_name='board_disable' LIMIT 1;"
}

SOURCE_CODE_VERSION="$(get_code_version "$PHPBB_PATH")"
SOURCE_DB_VERSION="$(get_db_version)"
[ -n "$SOURCE_CODE_VERSION" ] || fail 'Could not determine current phpBB code version.'
[ -n "$SOURCE_DB_VERSION" ] || fail 'Could not determine current phpBB database version.'
[ "$SOURCE_CODE_VERSION" = "$SOURCE_DB_VERSION" ] || fail "Code/DB version mismatch before upgrade: code=$SOURCE_CODE_VERSION db=$SOURCE_DB_VERSION"

case "$SOURCE_CODE_VERSION" in 3.3.*) ;; *) fail "Only phpBB 3.3.x source versions are supported. Found: $SOURCE_CODE_VERSION" ;; esac
case "$TARGET_VERSION" in 3.3.*) ;; *) fail "Only phpBB 3.3.x target versions are supported. Requested: $TARGET_VERSION" ;; esac

if [ -n "$EXPECTED_SOURCE_VERSION" ] && [ "$SOURCE_CODE_VERSION" != "$EXPECTED_SOURCE_VERSION" ]; then
    fail "Source version guard failed. Expected $EXPECTED_SOURCE_VERSION, found $SOURCE_CODE_VERSION"
fi
[ "$SOURCE_CODE_VERSION" != "$TARGET_VERSION" ] || fail "Board is already at target version $TARGET_VERSION"
php -r 'exit(version_compare($argv[1], $argv[2], "<") ? 0 : 1);' "$SOURCE_CODE_VERSION" "$TARGET_VERSION" \
    || fail "Refusing non-upgrade version transition: $SOURCE_CODE_VERSION -> $TARGET_VERSION"

say "Current phpBB version: $SOURCE_CODE_VERSION"
say "Requested target version: $TARGET_VERSION"

BOARD_DISABLED="$(get_board_disabled)"
say "Board maintenance flag (board_disable): ${BOARD_DISABLED:-<unknown>}"
if [ "$REQUIRE_MAINTENANCE" -eq 1 ] && [ "$BOARD_DISABLED" != '1' ]; then
    fail 'Remote production upgrade requires phpBB maintenance mode (board_disable=1).'
fi

# Validate explicitly preserved local paths before anything destructive.
if [ "$PRESERVE_PATH_COUNT" -gt 0 ]; then
    for rel in "${PRESERVE_PATHS[@]}"; do
        [ -n "$rel" ] || continue
        case "$rel" in
            /*|../*|*/../*|*/..|..) fail "Unsafe preserve path: $rel" ;;
            config.php|ext|ext/*|files|files/*|images|images/*|store|store/*)
                fail "Do not pass '$rel' via --preserve-path; phpBB's standard retained paths are handled internally."
                ;;
        esac
    done
fi

say "Extracting package into staging for validation with PHP ZipArchive..."
# Validate every archive entry before extraction. Reject absolute paths, backslashes,
# Windows drive-style paths, and any '..' path segment so an unexpected ZIP cannot
# write outside the staging directory.
php -r '
$zip = new ZipArchive();
$result = $zip->open($argv[1]);
if ($result !== true) {
    fwrite(STDERR, "Unable to open ZIP package (ZipArchive code " . $result . ").\n");
    exit(2);
}
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name === false || $name === "") {
        fwrite(STDERR, "ZIP contains an unreadable/empty entry name.\n");
        $zip->close();
        exit(3);
    }
    if (substr($name, 0, 1) === "/" || strpos($name, "\\") !== false || preg_match("/^[A-Za-z]:/", $name)) {
        fwrite(STDERR, "Unsafe ZIP entry: " . $name . "\n");
        $zip->close();
        exit(4);
    }
    foreach (explode("/", $name) as $part) {
        if ($part === "..") {
            fwrite(STDERR, "Unsafe ZIP entry: " . $name . "\n");
            $zip->close();
            exit(5);
        }
    }
}
if (!$zip->extractTo($argv[2])) {
    fwrite(STDERR, "ZipArchive extraction failed.\n");
    $zip->close();
    exit(6);
}
$zip->close();
' "$PACKAGE" "$EXTRACT_DIR" || fail 'Failed to safely extract phpBB package with PHP ZipArchive.' 

PACKAGE_ROOT=''
if [ -f "$EXTRACT_DIR/phpBB3/includes/constants.php" ]; then
    PACKAGE_ROOT="$EXTRACT_DIR/phpBB3"
elif [ -f "$EXTRACT_DIR/phpBB/includes/constants.php" ]; then
    PACKAGE_ROOT="$EXTRACT_DIR/phpBB"
elif [ -f "$EXTRACT_DIR/includes/constants.php" ]; then
    PACKAGE_ROOT="$EXTRACT_DIR"
else
    CONSTANTS_FILE="$(find "$EXTRACT_DIR" -maxdepth 4 -type f -path '*/includes/constants.php' | head -n 1 || true)"
    [ -n "$CONSTANTS_FILE" ] || fail 'Could not locate includes/constants.php in update package.'
    PACKAGE_ROOT="$(dirname "$(dirname "$CONSTANTS_FILE")")"
fi

[ -f "$PACKAGE_ROOT/index.php" ] || fail 'Package does not contain index.php at the detected phpBB root.'
[ -f "$PACKAGE_ROOT/bin/phpbbcli.php" ] || fail 'Package does not contain bin/phpbbcli.php.'
[ -d "$PACKAGE_ROOT/install" ] || fail 'Package does not contain install/.'
[ -d "$PACKAGE_ROOT/vendor" ] || fail 'Package does not contain vendor/.'

PACKAGE_VERSION="$(get_code_version "$PACKAGE_ROOT")"
[ "$PACKAGE_VERSION" = "$TARGET_VERSION" ] || fail "Package contents are version $PACKAGE_VERSION but filename/target says $TARGET_VERSION"
say "Package contents: phpBB $PACKAGE_VERSION (validated)"

SITE_KB="$(du -sk "$PHPBB_PATH" | awk '{print $1}')"
DB_KB="$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B "$DBNAME" -e 'SELECT COALESCE(ROUND(SUM(data_length + index_length) / 1024), 0) FROM information_schema.tables WHERE table_schema = DATABASE();')"
FREE_KB="$(df -Pk "$BACKUP_ROOT" | awk 'NR==2 {print $4}')"
SITE_KB="${SITE_KB:-0}"
DB_KB="${DB_KB:-0}"
FREE_KB="${FREE_KB:-0}"
REQUIRED_KB=$(( ((SITE_KB + DB_KB) * 125 / 100) + 262144 ))
say "Estimated site size: $((SITE_KB / 1024)) MB"
say "Estimated DB size:   $((DB_KB / 1024)) MB"
say "Backup-root free:    $((FREE_KB / 1024)) MB"
if [ "$FREE_KB" -lt "$REQUIRED_KB" ]; then
    fail "Insufficient free space under $BACKUP_ROOT. Conservative requirement: about $((REQUIRED_KB / 1024)) MB"
fi

say "phpBB full-package retained data paths: config.php, ext/, files/, images/, store/"
say "Implementation note: cache/ directory itself is also retained so Docker mount points are never removed; its contents are cleared."
if [ "$PRESERVE_PATH_COUNT" -gt 0 ]; then
    say "Additional project paths to preserve: ${PRESERVE_PATHS[*]}"
fi

if [ "$DRY_RUN" -eq 1 ]; then
    say ""
    say '=== DRY RUN PASSED ==='
    say "Would upgrade: $SOURCE_CODE_VERSION -> $TARGET_VERSION"
    say "Would create rollback backup: $BACKUP_DIR"
    say "Would follow the full-package core replacement pattern, restore project-specific preserved paths, run phpBB CLI db:migrate, verify versions, clear cache, and remove install/."
    exit 0
fi

say ""
say "=== Creating rollback backup ==="
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

# Capture cache ownership/mode because cache/ is replaced by the full-package procedure.
CACHE_META=''
if [ -d "$PHPBB_PATH/cache" ]; then
    CACHE_META="$(stat -c '%u:%g:%a' "$PHPBB_PATH/cache")"
fi

cat > "$BACKUP_DIR/upgrade_metadata.txt" <<EOF
run_id=$RUN_ID
phpbb_path=$PHPBB_PATH
source_version=$SOURCE_CODE_VERSION
target_version=$TARGET_VERSION
package_sha256=$PACKAGE_SHA
board_disable=$BOARD_DISABLED
cache_meta=$CACHE_META
created_utc=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF

say "Backing up database..."
run_mysqldump --single-transaction --quick "$DBNAME" > "$BACKUP_DIR/phpbb_db_backup.sql"
[ -s "$BACKUP_DIR/phpbb_db_backup.sql" ] || fail 'Database backup is empty.'

say "Backing up phpBB files..."
tar -C "$(dirname "$PHPBB_PATH")" -czpf "$BACKUP_DIR/phpbb_files_backup.tgz" "$(basename "$PHPBB_PATH")"
[ -s "$BACKUP_DIR/phpbb_files_backup.tgz" ] || fail 'Filesystem backup is empty.'
tar -tzf "$BACKUP_DIR/phpbb_files_backup.tgz" >/dev/null

BACKUP_COMPLETE=1
say "Rollback backup verified: $BACKUP_DIR"

say "Staging project-specific preserved paths..."
if [ "$PRESERVE_PATH_COUNT" -gt 0 ]; then
    for rel in "${PRESERVE_PATHS[@]}"; do
        [ -n "$rel" ] || continue
        src="$PHPBB_PATH/$rel"
        if [ -e "$src" ] || [ -L "$src" ]; then
            mkdir -p "$PRESERVE_DIR/$(dirname "$rel")"
            cp -a "$src" "$PRESERVE_DIR/$rel"
            say "  preserved: $rel"
        else
            say "  not present (skipped): $rel"
        fi
    done
fi

# Match phpBB's documented Full Package procedure exactly: these paths are
# retained from the existing board and must not be copied from the new package.
rm -f "$PACKAGE_ROOT/config.php"
rm -rf "$PACKAGE_ROOT/files" "$PACKAGE_ROOT/images" "$PACKAGE_ROOT/store"

say "Removing old phpBB core files (official full-package retention set kept in place)..."
MODIFIED=1
find "$PHPBB_PATH" -mindepth 1 -maxdepth 1 \
    ! -name 'config.php' \
    ! -name 'ext' \
    ! -name 'files' \
    ! -name 'images' \
    ! -name 'store' \
    ! -name 'cache' \
    -exec rm -rf -- {} +
if [ -d "$PHPBB_PATH/cache" ]; then
    find "$PHPBB_PATH/cache" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
fi

say "Installing phpBB $TARGET_VERSION full package..."
# Do not use cp -a here: newly created files should inherit target filesystem/SELinux context.
cp -R "$PACKAGE_ROOT"/. "$PHPBB_PATH"/

say "Restoring project-specific preserved paths..."
if [ "$PRESERVE_PATH_COUNT" -gt 0 ]; then
    for rel in "${PRESERVE_PATHS[@]}"; do
        [ -n "$rel" ] || continue
        saved="$PRESERVE_DIR/$rel"
        if [ -e "$saved" ] || [ -L "$saved" ]; then
            rm -rf "$PHPBB_PATH/$rel"
            mkdir -p "$PHPBB_PATH/$(dirname "$rel")"
            cp -a "$saved" "$PHPBB_PATH/$rel"
        fi
    done
fi

# cache/ was replaced; restore its previous directory owner/group/mode for Apache/PHP writes.
if [ -n "$CACHE_META" ] && [ -d "$PHPBB_PATH/cache" ]; then
    CACHE_UID="${CACHE_META%%:*}"
    CACHE_REST="${CACHE_META#*:}"
    CACHE_GID="${CACHE_REST%%:*}"
    CACHE_MODE="${CACHE_REST##*:}"
    chown -R "$CACHE_UID:$CACHE_GID" "$PHPBB_PATH/cache"
    chmod "$CACHE_MODE" "$PHPBB_PATH/cache"
fi


POST_REPLACE_CODE_VERSION="$(get_code_version "$PHPBB_PATH")"
[ "$POST_REPLACE_CODE_VERSION" = "$TARGET_VERSION" ] || fail "Code version after replacement is $POST_REPLACE_CODE_VERSION, expected $TARGET_VERSION"
say "Code replacement verified: phpBB $POST_REPLACE_CODE_VERSION"

say "Running phpBB database migrations..."
(
    cd "$PHPBB_PATH"
    php bin/phpbbcli.php db:migrate --safe-mode
)

POST_DB_VERSION="$(get_db_version)"
[ "$POST_DB_VERSION" = "$TARGET_VERSION" ] || fail "Database version after migration is $POST_DB_VERSION, expected $TARGET_VERSION"
say "Database migration verified: phpBB $POST_DB_VERSION"

say "Clearing phpBB cache..."
if [ -d "$PHPBB_PATH/cache" ]; then
    find "$PHPBB_PATH/cache" -mindepth 1 ! -name '.htaccess' ! -name 'index.htm' -exec rm -rf -- {} +
    if [ -n "$CACHE_META" ]; then
        chown "$CACHE_UID:$CACHE_GID" "$PHPBB_PATH/cache"
        chmod "$CACHE_MODE" "$PHPBB_PATH/cache"
    fi
fi

say "Removing install/ directory..."
rm -rf "$PHPBB_PATH/install"

FINAL_CODE_VERSION="$(get_code_version "$PHPBB_PATH")"
FINAL_DB_VERSION="$(get_db_version)"
[ "$FINAL_CODE_VERSION" = "$TARGET_VERSION" ] || fail "Final code version is $FINAL_CODE_VERSION, expected $TARGET_VERSION"
[ "$FINAL_DB_VERSION" = "$TARGET_VERSION" ] || fail "Final DB version is $FINAL_DB_VERSION, expected $TARGET_VERSION"
[ ! -d "$PHPBB_PATH/install" ] || fail 'install/ still exists after cleanup.'

MODIFIED=0
say ""
say "=== UPGRADE SUCCESSFUL ==="
say "phpBB: $SOURCE_CODE_VERSION -> $TARGET_VERSION"
say "Rollback backup retained at: $BACKUP_DIR"
say "Standard retained paths: config.php ext/ files/ images/ store/"
if [ "$PRESERVE_PATH_COUNT" -gt 0 ]; then
    say "Additional preserved paths: ${PRESERVE_PATHS[*]}"
else
    say "Additional preserved paths: <none>"
fi
exit 0
'@

try {
    Write-Step "=== Unified phpBB Upgrade Assistant ($ScriptBuild) ===`n"

    $Package = Get-SelectedPackage $Package
    $targetVersion = Get-PackageVersionFromName $Package
    $packageHash = (Get-FileHash -LiteralPath $Package -Algorithm SHA256).Hash.ToLowerInvariant()

    if ($ExpectedSourceVersion -and $ExpectedSourceVersion -notmatch '^3\.3\.[0-9]+(?:-[A-Za-z0-9._-]+)?$') {
        Fail "ExpectedSourceVersion must be a phpBB 3.3.x version. Got: $ExpectedSourceVersion"
    }

    Write-Step "Target: $Target" Green
    if ($Target -eq 'Docker') {
        Write-Step "Container: $ContainerName" Green
    } else {
        Write-Step "Server: $serverConnection (port $ServerPort)" Green
        if ($KeyPath) { Write-Step "SSH key: $KeyPath" Green }
    }
    Write-Step "phpBB path: $PhpbbPath" Green
    Write-Step "Package: $Package" Green
    Write-Step "Target version: $targetVersion" Green
    if ($ExpectedSourceVersion) { Write-Step "Expected source version: $ExpectedSourceVersion" Green }
    Write-Step "Package SHA-256: $packageHash" DarkGray
    if ($DryRun) { Write-Step 'DRY RUN: application files/database will not be modified.' Yellow }
    if ($NoRollback) { Write-Step 'WARNING: automatic rollback is DISABLED.' Red }
    Write-Host ''

    Write-Step '→ Testing target connection...'
    Test-TargetConnection
    $targetConnected = $true
    Write-Step '✅ Target connection successful' Green

    if (-not $DryRun -and -not $AutoConfirm) {
        if ($Target -eq 'Remote') {
            Write-Host ''
            Write-Host '⚠️  PRODUCTION UPGRADE' -ForegroundColor Yellow
            Write-Host 'The remote board must already be in phpBB maintenance mode.' -ForegroundColor Yellow
            Write-Host 'A full server-side database + filesystem backup will be created before changes.' -ForegroundColor Yellow
            Write-Host "The script will then upgrade the LIVE board to phpBB $targetVersion." -ForegroundColor Yellow
            $required = "UPGRADE $ServerHost"
            $confirm = Read-Host "Type exactly '$required' to continue"
            if ($confirm -ne $required) {
                Write-Step 'Upgrade cancelled.' Yellow
                exit 0
            }
        } else {
            $confirm = Read-Host "Upgrade local Docker phpBB to ${targetVersion}? (yes/no)"
            if ($confirm -ne 'yes') {
                Write-Step 'Upgrade cancelled.' Yellow
                exit 0
            }
        }
    }

    $localHelperScript = Join-Path $env:TEMP "phpbb-upgrade-$timestamp.sh"
    Write-Utf8NoBomLf -Path $localHelperScript -Content $targetProgram

    Write-Step '→ Copying validated upgrade engine to target...'
    Copy-ToTarget -LocalPath $localHelperScript -TargetPath $targetHelperScript

    Write-Step '→ Copying phpBB package to target...'
    Copy-ToTarget -LocalPath $Package -TargetPath $targetPackage

    $args = @(
        'bash', $targetHelperScript,
        '--phpbb-path', $PhpbbPath,
        '--package', $targetPackage,
        '--target-version', $targetVersion,
        '--package-sha', $packageHash,
        '--backup-root', $backupRoot,
        '--run-id', $timestamp
    )

    if ($ExpectedSourceVersion) {
        $args += @('--expected-source-version', $ExpectedSourceVersion)
    }
    foreach ($path in $PreservePath) {
        if (-not [string]::IsNullOrWhiteSpace($path) -and $path -ne 'config.php') {
            $args += @('--preserve-path', $path)
        }
    }
    if ($DryRun) { $args += '--dry-run' }
    if ($NoRollback) { $args += '--no-rollback' }
    if ($KeepStaging) { $args += '--keep-staging' }
    if ($Target -eq 'Remote' -and -not $DryRun) { $args += '--require-maintenance' }

    Write-Host ''
    Write-Step '→ Executing the SAME upgrade program on the target...'
    $exitCode = Invoke-TargetArgs -ArgumentList $args
    if ($exitCode -ne 0) {
        Fail "Target upgrade program failed with exit code $exitCode. Expected rollback backup location: $expectedBackupDir"
    }

    Write-Host ''
    if ($DryRun) {
        Write-Step '✅ DRY RUN PASSED' Green
        Write-Host 'No phpBB application files or database data were modified.'
    } else {
        Write-Step '✅ UPGRADE COMPLETED SUCCESSFULLY' Green
        Write-Host "Target: $Target"
        Write-Host "Version: $targetVersion"
        Write-Host "Rollback backup: $expectedBackupDir"
        if ($Target -eq 'Remote') {
            Write-Host ''
            Write-Host 'Keep maintenance mode enabled until you have smoke-tested the live forum and ACP.' -ForegroundColor Yellow
            Write-Host 'This script intentionally does NOT re-enable the board automatically.' -ForegroundColor Yellow
        }
    }
    Write-Host "Log: $LogPath"
}
catch {
    Write-Host ''
    Write-Host "❌ $($_.Exception.Message)" -ForegroundColor Red
    if (-not $DryRun) {
        Write-Host "Expected target backup location (if backup creation completed): $expectedBackupDir" -ForegroundColor Yellow
    }
    Write-Host "Log: $LogPath" -ForegroundColor DarkGray
    exit 1
}
finally {
    if ($localHelperScript -and (Test-Path -LiteralPath $localHelperScript)) {
        Remove-Item -LiteralPath $localHelperScript -Force -ErrorAction SilentlyContinue
    }
    if ($targetConnected) {
        try { Remove-TargetTempFiles } catch { }
    }
    try { Stop-Transcript | Out-Null } catch { }
}

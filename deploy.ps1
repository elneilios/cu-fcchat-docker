<#
.SYNOPSIS
    Deploy a local phpBB snapshot to a remote server with rollback protection.

.DESCRIPTION
    Deploys backups/<snapshot>/phpbb_db.sql and backups/<snapshot>/phpbb_files
    to a remote phpBB installation.

    Safety design:
      - validates the local snapshot before connecting
      - uses SSH BatchMode/key authentication
      - reads live DB credentials remotely with PHP
      - never places the DB password on a mysql/mysqldump command line
      - handles old mysqldump versions that do not support --no-tablespaces
      - stages and validates all incoming artifacts before touching live state
      - backs up the live database and files before modification
      - preserves environment-specific phpBB config rows from the live database
      - preserves the live config.php
      - automatically rolls back database/files if a deployment step fails
      - retains the live backup after success for manual recovery
      - cleans temporary deployment files from /tmp

    For a real deployment the board must normally already be disabled in phpBB.
    Use -AllowEnabledBoard only when you explicitly accept that risk (for
    example, an isolated Docker deployment test).

.PARAMETER SnapshotFolder
    Snapshot folder under backups/. If omitted, an interactive picker is shown.

.PARAMETER ServerHost
    Remote hostname or IP address.

.PARAMETER ServerUser
    SSH user. Default: root.

.PARAMETER ServerPort
    SSH port. Default: 22.

.PARAMETER PhpbbPath
    Remote phpBB path. Default: /var/www/html.

.PARAMETER KeyPath
    SSH private key.

.PARAMETER DryRun
    Run local and remote safety/pre-flight checks without uploading the snapshot,
    creating a persistent backup, or modifying phpBB/database content.

.PARAMETER SkipDatabase
    Deploy files only. A live files backup is still created.

.PARAMETER AllowEnabledBoard
    Permit a real deployment while phpBB board_disable is not 1.
    Intended primarily for isolated test targets.

.PARAMETER NoRollback
    Disable automatic rollback if a deployment step fails. Not recommended.

.PARAMETER NoHostKeyCheck
    Disable SSH host-key checking. Intended for disposable test targets only.

.PARAMETER AutoConfirm
    Skip the interactive DEPLOY confirmation.

.PARAMETER SkipPreflightChecks
    Retained for compatibility with deploy-test.ps1. Critical safety checks are
    never skipped; this switch only suppresses non-essential diagnostics.

.EXAMPLE
    .\deploy.ps1 -ServerHost cu-fcchat.com -KeyPath $HOME\.ssh\cu-fcchat-prod -SnapshotFolder 20260809_010300_hardening_test -DryRun

.EXAMPLE
    .\deploy.ps1 -ServerHost cu-fcchat.com -KeyPath $HOME\.ssh\cu-fcchat-prod -SnapshotFolder 20260809_010300_hardening_test

.EXAMPLE
    .\deploy.ps1 -ServerHost localhost -ServerPort 2222 -KeyPath .\.ssh\docker_test_ed25519 -SnapshotFolder 20260809_010300_hardening_test -AllowEnabledBoard
#>

[CmdletBinding()]
param(
    [string] $SnapshotFolder,
    [string] $ServerUser = 'root',
    [string] $ServerHost,
    [string] $PhpbbPath = '/var/www/html',
    [int]    $ServerPort = 22,
    [switch] $AutoConfirm,
    [string] $KeyPath,
    [switch] $DryRun,
    [switch] $NoHostKeyCheck,
    [string] $LogPath,
    [switch] $NoRollback,
    [switch] $SkipDatabase,
    [switch] $SkipPreflightChecks,
    [switch] $AllowEnabledBoard,
    [switch] $Help
)

$ErrorActionPreference = 'Stop'

try {
    chcp 65001 | Out-Null
    [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
}
catch {}

function Fail {
    param([string] $Message)
    throw $Message
}

function ConvertTo-PosixQuoted {
    param([AllowEmptyString()][string] $Value)

    $sq = [string][char]39
    $dq = [string][char]34
    $replacement = $sq + $dq + $sq + $dq + $sq
    return $sq + $Value.Replace($sq, $replacement) + $sq
}

function Write-Utf8NoBomLf {
    param(
        [string] $Path,
        [string] $Content
    )

    $lfContent = $Content -replace "`r`n", "`n"
    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $lfContent, $encoding)
}

function Assert-Tool {
    param([string] $Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        Fail "Required local tool is not available in PATH: $Name"
    }
}

if ($Help) {
    Get-Help $MyInvocation.MyCommand.Path -Detailed
    exit 0
}

Assert-Tool 'ssh'
Assert-Tool 'scp'
Assert-Tool 'tar'

if ([string]::IsNullOrWhiteSpace($ServerHost)) {
    $ServerHost = Read-Host 'Enter server address (hostname or IP)'
}

if ([string]::IsNullOrWhiteSpace($ServerHost)) {
    Fail 'ServerHost is required.'
}

if ($KeyPath) {
    $resolvedKey = Resolve-Path -LiteralPath $KeyPath -ErrorAction SilentlyContinue

    if (-not $resolvedKey) {
        Fail "SSH key not found: $KeyPath"
    }

    $KeyPath = $resolvedKey.Path
}

$backupsFolder = Join-Path $PSScriptRoot 'backups'

if (-not (Test-Path $backupsFolder -PathType Container)) {
    Fail "Backups folder not found: $backupsFolder"
}

$backupFolders = @(
    Get-ChildItem -LiteralPath $backupsFolder -Directory |
    Sort-Object Name -Descending |
    Select-Object -ExpandProperty Name
)

if ($backupFolders.Count -eq 0) {
    Fail 'No snapshot folders were found under backups/.'
}

if ([string]::IsNullOrWhiteSpace($SnapshotFolder)) {
    Write-Host ''
    Write-Host 'Available snapshots:' -ForegroundColor Yellow

    for ($i = 0; $i -lt $backupFolders.Count; $i++) {
        Write-Host "[$($i + 1)] $($backupFolders[$i])"
    }

    $selection = Read-Host "Select a snapshot by number (1-$($backupFolders.Count))"

    if ($selection -notmatch '^[1-9][0-9]*$') {
        Fail 'Invalid snapshot selection.'
    }

    $selectionIndex = [int]$selection - 1

    if ($selectionIndex -lt 0 -or $selectionIndex -ge $backupFolders.Count) {
        Fail 'Invalid snapshot selection.'
    }

    $SnapshotFolder = $backupFolders[$selectionIndex]
}
elseif (-not ($backupFolders -contains $SnapshotFolder)) {
    Fail "Snapshot folder not found: $SnapshotFolder"
}

$snapshotDir = Join-Path $backupsFolder $SnapshotFolder
$sqlFile = Join-Path $snapshotDir 'phpbb_db.sql'
$filesDir = Join-Path $snapshotDir 'phpbb_files'

if (-not $SkipDatabase) {
    if (-not (Test-Path $sqlFile -PathType Leaf)) {
        Fail "Snapshot database dump not found: $sqlFile"
    }

    if ((Get-Item $sqlFile).Length -eq 0) {
        Fail "Snapshot database dump is empty: $sqlFile"
    }
}

if (-not (Test-Path $filesDir -PathType Container)) {
    Fail "Snapshot phpBB files directory not found: $filesDir"
}

foreach ($requiredRelative in @('index.php', 'config.php', 'includes\constants.php')) {
    $requiredPath = Join-Path $filesDir $requiredRelative

    if (-not (Test-Path $requiredPath -PathType Leaf)) {
        Fail "Snapshot is missing required phpBB file: $requiredRelative"
    }
}

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'

if (-not $LogPath) {
    $logsDir = Join-Path $PSScriptRoot 'logs'
    New-Item -ItemType Directory -Path $logsDir -Force | Out-Null
    $LogPath = Join-Path $logsDir "deploy_$timestamp.log"
}

try {
    Start-Transcript -Path $LogPath -Force | Out-Null
}
catch {}

$serverConnection = "$ServerUser@$ServerHost"

$sshArgs = @('-o', 'BatchMode=yes')
$scpArgs = @('-o', 'BatchMode=yes')

if ($ServerPort) {
    $sshArgs += @('-p', $ServerPort)
    $scpArgs += @('-P', $ServerPort)
}

if ($KeyPath) {
    $sshArgs += @('-i', $KeyPath)
    $scpArgs += @('-i', $KeyPath)
}

if ($NoHostKeyCheck) {
    $hostKeyArgs = @('-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null')
    $sshArgs += $hostKeyArgs
    $scpArgs += $hostKeyArgs
}

$remoteStaging = "/tmp/phpbb-deploy-$timestamp"
$remoteHelper = "$remoteStaging/deploy-helper.sh"
$remoteSql = "$remoteStaging/snapshot.sql"
$remoteTar = "$remoteStaging/snapshot-files.tar.gz"
$remoteBackupDir = "/root/phpbb_deploy_backup_$timestamp"

$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) "phpbb_deploy_$timestamp"
$localHelper = Join-Path $tempRoot 'deploy-helper.sh'
$localTar = Join-Path $tempRoot 'snapshot-files.tar.gz'

$helper = @'
#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

PHPBB_PATH=''
STAGING_DIR=''
BACKUP_DIR=''
DO_DB=1
DRY_RUN=0
ALLOW_ENABLED_BOARD=0
NO_ROLLBACK=0
SKIP_OPTIONAL_PREFLIGHT=0

while [ "$#" -gt 0 ]; do
    case "$1" in
        --phpbb-path) PHPBB_PATH="$2"; shift 2 ;;
        --staging-dir) STAGING_DIR="$2"; shift 2 ;;
        --backup-dir) BACKUP_DIR="$2"; shift 2 ;;
        --skip-database) DO_DB=0; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        --allow-enabled-board) ALLOW_ENABLED_BOARD=1; shift ;;
        --no-rollback) NO_ROLLBACK=1; shift ;;
        --skip-optional-preflight) SKIP_OPTIONAL_PREFLIGHT=1; shift ;;
        *) printf 'ERROR: Unknown argument: %s\n' "$1" >&2; exit 2 ;;
    esac
done

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

[ -n "$PHPBB_PATH" ] || fail 'phpBB path was not supplied.'
[ -n "$STAGING_DIR" ] || fail 'staging directory was not supplied.'
[ -n "$BACKUP_DIR" ] || fail 'backup directory was not supplied.'
[ -d "$PHPBB_PATH" ] || fail "phpBB directory does not exist: $PHPBB_PATH"
[ -f "$PHPBB_PATH/config.php" ] || fail "Live config.php is missing: $PHPBB_PATH/config.php"
[ -f "$PHPBB_PATH/index.php" ] || fail "Live index.php is missing: $PHPBB_PATH/index.php"
[ -f "$PHPBB_PATH/includes/constants.php" ] || fail 'Live constants.php is missing.'

for tool in php tar mysql chown chmod; do
    command -v "$tool" >/dev/null 2>&1 || fail "Required remote tool is missing: $tool"
done

if [ "$DO_DB" -eq 1 ]; then
    command -v mysqldump >/dev/null 2>&1 || fail 'Required remote tool is missing: mysqldump'
fi

PHPBB_PARENT=$(dirname "$PHPBB_PATH")
PHPBB_NAME=$(basename "$PHPBB_PATH")
NEW_DIR="${PHPBB_PATH}.deploy-new-$(date +%s)"
OLD_DIR="${PHPBB_PATH}.deploy-old-$(date +%s)"
MYSQL_CNF="$STAGING_DIR/mysql.cnf"
DB_NAME_FILE="$STAGING_DIR/dbname"
DB_META_FILE="$STAGING_DIR/dbmeta"
TABLE_PREFIX_FILE="$STAGING_DIR/tableprefix"
READ_CONFIG_PHP="$STAGING_DIR/read-config.php"
ENV_CONFIG_SQL="$BACKUP_DIR/environment_config.sql"
DB_BACKUP_SQL="$BACKUP_DIR/phpbb_db_backup.sql"
FILES_BACKUP_TGZ="$BACKUP_DIR/phpbb_files_backup.tgz"
WRITABLE_META="$STAGING_DIR/writable-directories.tsv"

DB_CHANGED=0
FILES_SWAP_STARTED=0
BACKUP_READY=0
IS_MOUNTPOINT=0

if command -v mountpoint >/dev/null 2>&1 && mountpoint -q "$PHPBB_PATH"; then
    IS_MOUNTPOINT=1
fi

write_config_helper() {
    cat > "$READ_CONFIG_PHP" <<'PHP'
<?php
if ($argc < 6) {
    fwrite(STDERR, "Invalid helper invocation\n");
    exit(2);
}

$configPath = $argv[1];
$cnfPath = $argv[2];
$dbNamePath = $argv[3];
$metaPath = $argv[4];
$tablePrefixPath = $argv[5];

include $configPath;

if (!isset($dbname) || $dbname === '' || !isset($dbuser) || $dbuser === '') {
    fwrite(STDERR, "Database name/user missing from config.php\n");
    exit(3);
}

$host = isset($dbhost) && $dbhost !== '' ? $dbhost : 'localhost';
$port = isset($dbport) ? (string) $dbport : '';
$password = isset($dbpasswd) ? (string) $dbpasswd : '';
$prefix = isset($table_prefix) ? (string) $table_prefix : 'phpbb_';

function cnf_quote($value) {
    $value = str_replace("\\", "\\\\", (string) $value);
    $value = str_replace('"', '\\"', $value);
    $value = str_replace("\r", "\\r", $value);
    $value = str_replace("\n", "\\n", $value);
    $value = str_replace("\t", "\\t", $value);
    return '"' . $value . '"';
}

$cnf = "[client]\n";
$cnf .= "host=" . cnf_quote($host) . "\n";
$cnf .= "user=" . cnf_quote($dbuser) . "\n";
$cnf .= "password=" . cnf_quote($password) . "\n";

if ($port !== '') {
    $cnf .= "port=" . cnf_quote($port) . "\n";
}

if (file_put_contents($cnfPath, $cnf) === false) {
    fwrite(STDERR, "Could not create MySQL option file\n");
    exit(4);
}

file_put_contents($dbNamePath, (string) $dbname);
file_put_contents($tablePrefixPath, $prefix);
file_put_contents(
    $metaPath,
    "Database: " . $dbname . "\n" .
    "Host: " . $host . "\n" .
    "User: " . $dbuser . "\n" .
    "Table prefix: " . $prefix . "\n"
);
PHP
}

write_config_helper
php "$READ_CONFIG_PHP" "$PHPBB_PATH/config.php" "$MYSQL_CNF" "$DB_NAME_FILE" "$DB_META_FILE" "$TABLE_PREFIX_FILE"
chmod 600 "$MYSQL_CNF"

DB_NAME=$(cat "$DB_NAME_FILE")
TABLE_PREFIX=$(cat "$TABLE_PREFIX_FILE")
CONFIG_TABLE="${TABLE_PREFIX}config"
SESSIONS_TABLE="${TABLE_PREFIX}sessions"
SESSION_KEYS_TABLE="${TABLE_PREFIX}sessions_keys"

mysql_exec() {
    mysql --defaults-extra-file="$MYSQL_CNF" "$@" "$DB_NAME"
}

dump_supports_no_tablespaces() {
    mysqldump --help 2>&1 | grep -q -- '--no-tablespaces'
}

run_mysqldump() {
    if dump_supports_no_tablespaces; then
        mysqldump --defaults-extra-file="$MYSQL_CNF" --no-tablespaces "$@"
    else
        mysqldump --defaults-extra-file="$MYSQL_CNF" "$@"
    fi
}

clear_tree_preserving_direct_mounts() {
    local root="$1"
    local entry

    # Docker/dev targets can have nested volumes mounted directly beneath the
    # phpBB root (for example cache, files and store). Removing those directory
    # entries fails with EBUSY. Clear their contents while preserving the mount
    # points themselves; remove ordinary entries normally.
    shopt -s dotglob nullglob

    for entry in "$root"/*; do
        if command -v mountpoint >/dev/null 2>&1 && mountpoint -q "$entry"; then
            find "$entry" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
        else
            rm -rf -- "$entry"
        fi
    done

    shopt -u dotglob nullglob
}

capture_writable_metadata() {
    local rel
    local target
    local uid
    local gid
    local mode

    : > "$WRITABLE_META"

    for rel in cache files store images/avatars/upload; do
        target="$PHPBB_PATH/$rel"

        if [ -e "$target" ]; then
            uid=$(stat -c '%u' "$target")
            gid=$(stat -c '%g' "$target")
            mode=$(stat -c '%a' "$target")
            printf '%s\t%s\t%s\t%s\n' "$rel" "$uid" "$gid" "$mode" >> "$WRITABLE_META"
        fi
    done
}

restore_writable_metadata() {
    local rel
    local uid
    local gid
    local mode
    local target

    [ -s "$WRITABLE_META" ] || return 0

    while IFS=$'\t' read -r rel uid gid mode; do
        [ -n "$rel" ] || continue
        target="$PHPBB_PATH/$rel"

        if [ -e "$target" ]; then
            # Snapshot tar metadata belongs to the source environment. phpBB's
            # writable trees must retain the target environment's ownership.
            chown -R "$uid:$gid" "$target"
            chmod "$mode" "$target"
        fi
    done < "$WRITABLE_META"
}

rollback() {
    local reason="$1"

    trap - ERR
    set +e

    printf '\nDEPLOYMENT FAILED: %s\n' "$reason" >&2

    if [ "$NO_ROLLBACK" -eq 1 ]; then
        printf 'Automatic rollback disabled. Backup location: %s\n' "$BACKUP_DIR" >&2
        return
    fi

    if [ "$BACKUP_READY" -ne 1 ]; then
        printf 'No live modifications were made; rollback is not required.\n' >&2
        return
    fi

    printf 'Starting automatic rollback...\n' >&2

    if [ "$FILES_SWAP_STARTED" -eq 1 ]; then
        printf '  Restoring live phpBB files...\n' >&2

        if [ "$IS_MOUNTPOINT" -eq 1 ]; then
            clear_tree_preserving_direct_mounts "$PHPBB_PATH"
            tar -xzf "$FILES_BACKUP_TGZ" -C "$PHPBB_PATH"
        else
            rm -rf "$PHPBB_PATH"

            if [ -d "$OLD_DIR" ]; then
                mv "$OLD_DIR" "$PHPBB_PATH"
            else
                mkdir -p "$PHPBB_PATH"
                tar -xzf "$FILES_BACKUP_TGZ" -C "$PHPBB_PATH"
            fi
        fi

        restore_writable_metadata
    fi

    if [ "$DB_CHANGED" -eq 1 ] && [ "$DO_DB" -eq 1 ]; then
        printf '  Restoring live database...\n' >&2
        mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" < "$DB_BACKUP_SQL"
    fi

    if [ -d "$PHPBB_PATH/cache" ]; then
        find "$PHPBB_PATH/cache" -mindepth 1 \
            ! -name '.htaccess' ! -name 'index.htm' \
            -exec rm -rf -- {} + 2>/dev/null
    fi

    printf 'Automatic rollback finished. Verify the site manually.\n' >&2
}

on_error() {
    local rc=$?
    local line=${BASH_LINENO[0]:-unknown}
    rollback "command failed at helper line $line (exit $rc)"
    exit "$rc"
}

trap on_error ERR

LIVE_CODE_VERSION=$(
    php -r 'define("IN_PHPBB", true); $table_prefix = "phpbb_"; include $argv[1]; echo PHPBB_VERSION;' \
        "$PHPBB_PATH/includes/constants.php"
)

mysql_exec -N -e 'SELECT 1' >/dev/null

LIVE_DB_VERSION=$(
    mysql_exec -N -e "SELECT config_value FROM \`$CONFIG_TABLE\` WHERE config_name='version' LIMIT 1;" |
    head -n 1
)

BOARD_DISABLED=$(
    mysql_exec -N -e "SELECT config_value FROM \`$CONFIG_TABLE\` WHERE config_name='board_disable' LIMIT 1;" |
    head -n 1
)

printf 'Remote phpBB code: %s\n' "$LIVE_CODE_VERSION"
printf 'Remote phpBB DB:   %s\n' "${LIVE_DB_VERSION:-unknown}"
cat "$DB_META_FILE"

capture_writable_metadata

if [ "$BOARD_DISABLED" = '1' ]; then
    printf 'Maintenance mode: ON\n'
else
    printf 'Maintenance mode: OFF\n'

    if [ "$DRY_RUN" -eq 0 ] && [ "$ALLOW_ENABLED_BOARD" -ne 1 ]; then
        fail 'phpBB board is enabled. Disable it before a real deployment, or explicitly use -AllowEnabledBoard.'
    fi
fi

touch "$PHPBB_PARENT/.phpbb_deploy_write_test"
rm -f "$PHPBB_PARENT/.phpbb_deploy_write_test"

if [ "$SKIP_OPTIONAL_PREFLIGHT" -ne 1 ]; then
    AVAILABLE_KB=$(df -Pk "$PHPBB_PARENT" | awk 'NR==2 {print $4}')
    printf 'Available filesystem space: %s KB\n' "${AVAILABLE_KB:-unknown}"
fi

if [ "$DO_DB" -eq 1 ]; then
    PROBE_SQL="$STAGING_DIR/mysqldump-probe.sql"
    run_mysqldump --no-data --skip-triggers "$DB_NAME" > "$PROBE_SQL"
    test -s "$PROBE_SQL" || fail 'mysqldump compatibility probe produced an empty file.'

    if dump_supports_no_tablespaces; then
        printf 'mysqldump: --no-tablespaces supported\n'
    else
        printf 'mysqldump: standard options required (--no-tablespaces unavailable)\n'
    fi

    printf 'Database dump probe: OK\n'
fi

if [ "$DRY_RUN" -eq 1 ]; then
    printf 'Remote write/pre-flight checks: OK\n'
    printf 'DRY RUN PASSED\n'
    exit 0
fi

SNAPSHOT_TAR="$STAGING_DIR/snapshot-files.tar.gz"
SNAPSHOT_SQL="$STAGING_DIR/snapshot.sql"

test -s "$SNAPSHOT_TAR" || fail 'Staged snapshot file tarball is missing or empty.'
tar -tzf "$SNAPSHOT_TAR" >/dev/null

if [ "$DO_DB" -eq 1 ]; then
    test -s "$SNAPSHOT_SQL" || fail 'Staged snapshot database dump is missing or empty.'
fi

rm -rf "$NEW_DIR" "$OLD_DIR"
mkdir -p "$NEW_DIR"
tar -xzf "$SNAPSHOT_TAR" -C "$NEW_DIR"

test -f "$NEW_DIR/index.php" || fail 'Extracted snapshot is missing index.php.'
test -f "$NEW_DIR/config.php" || fail 'Extracted snapshot is missing config.php.'
test -f "$NEW_DIR/includes/constants.php" || fail 'Extracted snapshot is missing constants.php.'

# Never deploy local/Docker DB credentials to the live target.
cp -p "$PHPBB_PATH/config.php" "$NEW_DIR/config.php"

# A deployed cache is stale by definition; preserve only phpBB's protective files.
if [ -d "$NEW_DIR/cache" ]; then
    find "$NEW_DIR/cache" -mindepth 1 \
        ! -name '.htaccess' ! -name 'index.htm' \
        -exec rm -rf -- {} +
fi

LIVE_OWNER=$(stat -c '%U' "$PHPBB_PATH" 2>/dev/null || printf 'apache')
LIVE_GROUP=$(stat -c '%G' "$PHPBB_PATH" 2>/dev/null || printf 'apache')
LIVE_CONFIG_OWNER=$(stat -c '%U' "$PHPBB_PATH/config.php" 2>/dev/null || printf '%s' "$LIVE_OWNER")
LIVE_CONFIG_GROUP=$(stat -c '%G' "$PHPBB_PATH/config.php" 2>/dev/null || printf '%s' "$LIVE_GROUP")
LIVE_CONFIG_MODE=$(stat -c '%a' "$PHPBB_PATH/config.php" 2>/dev/null || printf '640')

chown -R "$LIVE_OWNER:$LIVE_GROUP" "$NEW_DIR" 2>/dev/null || true
find "$NEW_DIR" -type d -exec chmod 755 {} +
find "$NEW_DIR" -type f -exec chmod 644 {} +

# The web-server owner can write these directories with 755. Preserve the
# live config.php ownership/mode rather than broadening credential visibility.
chown "$LIVE_CONFIG_OWNER:$LIVE_CONFIG_GROUP" "$NEW_DIR/config.php" 2>/dev/null || true
chmod "$LIVE_CONFIG_MODE" "$NEW_DIR/config.php" 2>/dev/null || true

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

printf 'Creating live files backup...\n'
tar -C "$PHPBB_PATH" -czf "$FILES_BACKUP_TGZ" .
test -s "$FILES_BACKUP_TGZ" || fail 'Live files backup is empty.'
tar -tzf "$FILES_BACKUP_TGZ" >/dev/null

if [ "$DO_DB" -eq 1 ]; then
    printf 'Creating live database backup...\n'
    run_mysqldump "$DB_NAME" > "$DB_BACKUP_SQL"
    test -s "$DB_BACKUP_SQL" || fail 'Live database backup is empty.'

    # These values describe the deployment environment, not the snapshot.
    # Restore them after importing the snapshot DB so HTTP/HTTPS/host/cookie
    # settings (and maintenance state) survive unchanged.
    ENV_KEYS="'server_name','server_protocol','server_port','script_path','force_server_vars','cookie_domain','cookie_path','cookie_secure','ip_check','check_browser','board_disable','board_disable_msg'"

    run_mysqldump \
        --replace \
        --no-create-info \
        --skip-triggers \
        --where="config_name IN ($ENV_KEYS)" \
        "$DB_NAME" "$CONFIG_TABLE" > "$ENV_CONFIG_SQL"

    test -s "$ENV_CONFIG_SQL" || fail 'Environment config backup is empty.'
fi

{
    printf 'Created: %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    printf 'phpBB path: %s\n' "$PHPBB_PATH"
    printf 'phpBB code before deploy: %s\n' "$LIVE_CODE_VERSION"
    printf 'phpBB DB before deploy: %s\n' "${LIVE_DB_VERSION:-unknown}"
    cat "$DB_META_FILE"
} > "$BACKUP_DIR/metadata.txt"

if command -v sha256sum >/dev/null 2>&1; then
    (
        cd "$BACKUP_DIR"
        if [ "$DO_DB" -eq 1 ]; then
            sha256sum phpbb_db_backup.sql phpbb_files_backup.tgz environment_config.sql > backup_manifest.sha256
        else
            sha256sum phpbb_files_backup.tgz > backup_manifest.sha256
        fi
    )
fi

BACKUP_READY=1
printf 'Live backup complete: %s\n' "$BACKUP_DIR"

if [ "$DO_DB" -eq 1 ]; then
    printf 'Importing snapshot database...\n'
    DB_CHANGED=1

    # Newer MariaDB dump clients may emit sandbox-mode lines that MySQL 5.6
    # does not understand. Remove only those client-specific comment commands.
    grep -v '^/\*M!' "$SNAPSHOT_SQL" |
        mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME"

    printf 'Restoring live environment configuration...\n'
    mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" < "$ENV_CONFIG_SQL"

    # Snapshot sessions should never become live sessions.
    mysql_exec -e \
        "TRUNCATE TABLE \`$SESSIONS_TABLE\`; TRUNCATE TABLE \`$SESSION_KEYS_TABLE\`;"
fi

printf 'Replacing phpBB files...\n'
FILES_SWAP_STARTED=1

if [ "$IS_MOUNTPOINT" -eq 1 ]; then
    # Docker/dev targets may bind-mount the phpBB directory and can also have
    # nested volume mount points. Preserve those mount points while replacing
    # their contents and the rest of the phpBB tree.
    clear_tree_preserving_direct_mounts "$PHPBB_PATH"
    tar -C "$NEW_DIR" -cf - . | tar -C "$PHPBB_PATH" -xf -
    rm -rf "$NEW_DIR"
else
    # Production-style filesystem: keep the old tree next to the new one until
    # post-deploy validation passes.
    mv "$PHPBB_PATH" "$OLD_DIR"
    mv "$NEW_DIR" "$PHPBB_PATH"
fi

restore_writable_metadata

test -f "$PHPBB_PATH/index.php" || fail 'Deployed phpBB tree is missing index.php.'
test -f "$PHPBB_PATH/config.php" || fail 'Deployed phpBB tree is missing config.php.'

DEPLOYED_CODE_VERSION=$(
    php -r 'define("IN_PHPBB", true); $table_prefix = "phpbb_"; include $argv[1]; echo PHPBB_VERSION;' \
        "$PHPBB_PATH/includes/constants.php"
)

DEPLOYED_DB_VERSION=$(
    mysql_exec -N -e "SELECT config_value FROM \`$CONFIG_TABLE\` WHERE config_name='version' LIMIT 1;" |
    head -n 1
)

if [ "$DO_DB" -eq 1 ] && [ -n "$DEPLOYED_DB_VERSION" ] &&
   [ "$DEPLOYED_CODE_VERSION" != "$DEPLOYED_DB_VERSION" ]; then
    fail "Post-deploy version mismatch: code=$DEPLOYED_CODE_VERSION db=$DEPLOYED_DB_VERSION"
fi

if [ -d "$PHPBB_PATH/cache" ]; then
    find "$PHPBB_PATH/cache" -mindepth 1 \
        ! -name '.htaccess' ! -name 'index.htm' \
        -exec rm -rf -- {} + 2>/dev/null
fi

if [ "$IS_MOUNTPOINT" -ne 1 ] && [ -d "$OLD_DIR" ]; then
    rm -rf "$OLD_DIR"
fi

FILES_SWAP_STARTED=0
DB_CHANGED=0

DB_HOST=$(awk -F': ' '/^Host:/{print $2; exit}' "$DB_META_FILE")
DB_USER=$(awk -F': ' '/^User:/{print $2; exit}' "$DB_META_FILE")

printf 'Deployed phpBB code: %s\n' "$DEPLOYED_CODE_VERSION"
printf 'Deployed phpBB DB:   %s\n' "${DEPLOYED_DB_VERSION:-unknown}"
printf 'DEPLOYMENT PASSED\n'
printf 'Backup retained: %s\n' "$BACKUP_DIR"
if [ "$IS_MOUNTPOINT" -eq 1 ]; then
    printf 'Manual files rollback:\n'
    printf '  Target is a mount point and may contain nested mounts (for example cache/files/store).\n'
    printf '  Preserve those mount points when restoring: %s\n' "$FILES_BACKUP_TGZ"
    printf '  Do NOT use a blanket rm -rf of %s/*.\n' "$PHPBB_PATH"
else
    printf 'Manual files rollback (remote shell):\n'
    printf '  find %q -mindepth 1 -maxdepth 1 -exec rm -rf -- {} + && tar -xzf %q -C %q\n' \
        "$PHPBB_PATH" "$FILES_BACKUP_TGZ" "$PHPBB_PATH"
fi

if [ "$DO_DB" -eq 1 ]; then
    printf 'Manual DB rollback (remote shell; prompts for password):\n'
    printf '  mysql -h %q -u %q -p %q < %q\n' \
        "$DB_HOST" "$DB_USER" "$DB_NAME" "$DB_BACKUP_SQL"
fi
'@

New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
Write-Utf8NoBomLf -Path $localHelper -Content $helper

Write-Host ''
Write-Host '=== phpBB Snapshot Deployment ===' -ForegroundColor Cyan
Write-Host ''
Write-Host "Snapshot:     $SnapshotFolder"
Write-Host "Target:       $serverConnection (port $ServerPort)"
Write-Host "phpBB path:   $PhpbbPath"
Write-Host "Database:     $(-not $SkipDatabase)"
Write-Host "Auto rollback:$(-not $NoRollback)"
Write-Host "Log:          $LogPath"

if ($DryRun) {
    Write-Host 'Mode:         DRY RUN' -ForegroundColor Yellow
}

if ($SkipPreflightChecks) {
    Write-Host 'Optional diagnostics suppressed; critical checks remain enabled.' -ForegroundColor Yellow
}

Write-Host ''

$remoteStagingCreated = $false

try {
    Write-Host '→ Testing SSH connection...'
    & ssh @sshArgs $serverConnection 'echo ok' | Out-Null

    if ($LASTEXITCODE -ne 0) {
        Fail "SSH connection failed for $serverConnection."
    }

    Write-Host '✅ SSH connection successful'

    Write-Host '→ Creating temporary remote staging directory...'
    $mkdirCommand = 'mkdir -p ' + (ConvertTo-PosixQuoted $remoteStaging)
    & ssh @sshArgs $serverConnection $mkdirCommand | Out-Null

    if ($LASTEXITCODE -ne 0) {
        Fail 'Could not create the remote staging directory.'
    }

    $remoteStagingCreated = $true

    Write-Host '→ Uploading deployment helper...'
    & scp @scpArgs $localHelper "$serverConnection`:$remoteHelper" | Out-Null

    if ($LASTEXITCODE -ne 0) {
        Fail 'Could not upload the deployment helper.'
    }

    $helperArgs = @(
        $remoteHelper,
        '--phpbb-path', $PhpbbPath,
        '--staging-dir', $remoteStaging,
        '--backup-dir', $remoteBackupDir
    )

    if ($SkipDatabase) {
        $helperArgs += '--skip-database'
    }

    if ($DryRun) {
        $helperArgs += '--dry-run'
    }

    if ($AllowEnabledBoard) {
        $helperArgs += '--allow-enabled-board'
    }

    if ($NoRollback) {
        $helperArgs += '--no-rollback'
    }

    if ($SkipPreflightChecks) {
        $helperArgs += '--skip-optional-preflight'
    }

    $remoteCommand = 'bash ' + (($helperArgs | ForEach-Object { ConvertTo-PosixQuoted $_ }) -join ' ')

    # Always perform the first remote invocation in dry-run mode. A real
    # deployment is invoked only after all snapshot artifacts are uploaded
    # and their hashes have been verified.
    $preflightArgs = @($helperArgs)

    if (-not $DryRun) {
        $preflightArgs += '--dry-run'
    }

    $preflightCommand = 'bash ' + (($preflightArgs | ForEach-Object { ConvertTo-PosixQuoted $_ }) -join ' ')

    Write-Host '→ Running remote pre-flight...'
    & ssh @sshArgs $serverConnection $preflightCommand | Out-Host

    if ($LASTEXITCODE -ne 0) {
        Fail 'Remote deployment pre-flight failed.'
    }

    if ($DryRun) {
        Write-Host ''
        Write-Host '✅ DEPLOY DRY RUN PASSED' -ForegroundColor Green
        Write-Host 'No snapshot artifacts were uploaded and no live phpBB/database content was modified.'
        exit 0
    }

    if (-not $AutoConfirm) {
        Write-Host ''
        Write-Host 'WARNING: This is a REAL deployment.' -ForegroundColor Yellow
        Write-Host "A live rollback backup will be retained at: $remoteBackupDir"

        if (-not $SkipDatabase) {
            Write-Host 'The live database will be replaced by the snapshot database.'
        }

        Write-Host 'The live phpBB filesystem will be replaced by the snapshot filesystem.'
        Write-Host ''
        $confirmation = Read-Host "Type exactly 'DEPLOY' to continue"

        if ($confirmation -cne 'DEPLOY') {
            Write-Host 'Deployment cancelled.'
            exit 0
        }
    }

    Write-Host ''
    Write-Host '→ Creating local snapshot tarball...'
    & tar -C $filesDir -czf $localTar .

    if ($LASTEXITCODE -ne 0) {
        Fail 'Could not create the local phpBB snapshot tarball.'
    }

    if (-not (Test-Path $localTar) -or (Get-Item $localTar).Length -eq 0) {
        Fail 'Local phpBB snapshot tarball is missing or empty.'
    }

    Write-Host '✅ Local phpBB tarball created'

    if (-not $SkipDatabase) {
        Write-Host '→ Uploading snapshot database...'
        & scp @scpArgs $sqlFile "$serverConnection`:$remoteSql"

        if ($LASTEXITCODE -ne 0) {
            Fail 'Snapshot database upload failed.'
        }

        Write-Host '✅ Snapshot database uploaded'
    }

    Write-Host '→ Uploading snapshot phpBB files...'
    & scp @scpArgs $localTar "$serverConnection`:$remoteTar"

    if ($LASTEXITCODE -ne 0) {
        Fail 'Snapshot file upload failed.'
    }

    Write-Host '✅ Snapshot phpBB files uploaded'

    # Verify transfer integrity whenever the remote host has sha256sum.
    $shaToolCheck = & ssh @sshArgs $serverConnection 'command -v sha256sum >/dev/null 2>&1 && echo yes || echo no'

    if (($shaToolCheck | Out-String).Trim() -eq 'yes') {
        Write-Host '→ Verifying uploaded artifact hashes...'

        if (-not $SkipDatabase) {
            $localDbHash = (Get-FileHash -LiteralPath $sqlFile -Algorithm SHA256).Hash.ToLowerInvariant()
            $remoteDbHashCommand = 'sha256sum ' + (ConvertTo-PosixQuoted $remoteSql) + " | awk '{print `$1}'"
            $remoteDbHash = (& ssh @sshArgs $serverConnection $remoteDbHashCommand | Out-String).Trim().ToLowerInvariant()

            if ($LASTEXITCODE -ne 0 -or $localDbHash -ne $remoteDbHash) {
                Fail 'Uploaded database SHA-256 verification failed.'
            }
        }

        $localTarHash = (Get-FileHash -LiteralPath $localTar -Algorithm SHA256).Hash.ToLowerInvariant()
        $remoteTarHashCommand = 'sha256sum ' + (ConvertTo-PosixQuoted $remoteTar) + " | awk '{print `$1}'"
        $remoteTarHash = (& ssh @sshArgs $serverConnection $remoteTarHashCommand | Out-String).Trim().ToLowerInvariant()

        if ($LASTEXITCODE -ne 0 -or $localTarHash -ne $remoteTarHash) {
            Fail 'Uploaded phpBB tarball SHA-256 verification failed.'
        }

        Write-Host '✅ Uploaded artifact hashes match'
    }
    else {
        Write-Host '⚠️ Remote sha256sum unavailable; helper validation will still verify archive readability.' -ForegroundColor Yellow
    }

    Write-Host ''
    Write-Host '→ Running deployment engine...'
    & ssh @sshArgs $serverConnection $remoteCommand | Out-Host

    if ($LASTEXITCODE -ne 0) {
        Fail "Deployment engine failed. Inspect the output above and backup directory: $remoteBackupDir"
    }

    Write-Host ''
    Write-Host '════════════════════════════════════════════════' -ForegroundColor Green
    Write-Host '✅ DEPLOYMENT SUCCESSFUL' -ForegroundColor Green
    Write-Host '════════════════════════════════════════════════'
    Write-Host ''
    Write-Host "Snapshot: $SnapshotFolder"
    Write-Host "Target:   $serverConnection"
    Write-Host "Backup:   $remoteBackupDir"
    Write-Host "Log:      $LogPath"
    Write-Host ''
    Write-Host 'The deployment preserved the target server configuration and maintenance state.'
    Write-Host 'Test the live site and ACP before re-enabling the board.'
    Write-Host ''
    Write-Host 'Manual recovery backup contents:'
    Write-Host "  $remoteBackupDir/phpbb_files_backup.tgz"

    if (-not $SkipDatabase) {
        Write-Host "  $remoteBackupDir/phpbb_db_backup.sql"
        Write-Host "  $remoteBackupDir/environment_config.sql"
    }

    Write-Host "  $remoteBackupDir/metadata.txt"
    Write-Host "  $remoteBackupDir/backup_manifest.sha256 (when sha256sum is available)"
}
finally {
    if ($remoteStagingCreated) {
        $cleanupCommand = 'rm -rf ' + (ConvertTo-PosixQuoted $remoteStaging)
        & ssh @sshArgs $serverConnection $cleanupCommand 2>$null | Out-Null
    }

    if (Test-Path $tempRoot) {
        Remove-Item -LiteralPath $tempRoot -Recurse -Force -ErrorAction SilentlyContinue
    }

    try {
        Stop-Transcript | Out-Null
    }
    catch {}
}

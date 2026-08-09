<#
.SYNOPSIS
    Pulls a production phpBB installation into the local Docker workspace.

.DESCRIPTION
    Downloads the live phpBB filesystem and/or database over SSH without modifying
    the production application or database.

    Production-side work is limited to temporary files under /tmp:
      - a temporary helper script
      - a temporary database dump (when database pull is enabled)
      - a temporary phpBB tarball (when code pull is enabled)

    Database credentials are read by PHP on the remote host from phpBB config.php.
    The password is written only to a temporary mode-600 MySQL option file on the
    remote host and is never placed on the mysqldump command line.

    All requested artifacts are staged and validated locally before the existing
    local phpbb/ tree or db_init/001_phpbb_backup.sql are replaced.

.PARAMETER ServerHost
    Remote server hostname or IP address.

.PARAMETER ServerUser
    SSH user. Default: root.

.PARAMETER ServerPort
    SSH port. Default: 22.

.PARAMETER KeyPath
    SSH private-key path.

.PARAMETER PhpbbPath
    Absolute path to phpBB on the remote server. Default: /var/www/html.

.PARAMETER NoHostKeyCheck
    Disable SSH host-key checking. Intended for disposable test targets only.

.PARAMETER SkipCode
    Pull only the database.

.PARAMETER SkipDatabase
    Pull only the phpBB filesystem.

.PARAMETER DryRun
    Run remote/local pre-flight validation without creating dumps/tarballs or
    replacing local files.

.PARAMETER AutoConfirm
    Skip the interactive PULL confirmation for a real pull.

.EXAMPLE
    .\pull-live.ps1 -ServerHost cu-fcchat.com -KeyPath $HOME\.ssh\cu-fcchat-prod -DryRun

.EXAMPLE
    .\pull-live.ps1 -ServerHost cu-fcchat.com -KeyPath $HOME\.ssh\cu-fcchat-prod

.EXAMPLE
    .\pull-live.ps1 -ServerHost cu-fcchat.com -KeyPath $HOME\.ssh\cu-fcchat-prod -SkipCode
#>

[CmdletBinding()]
param(
    [string] $ServerHost,
    [string] $ServerUser = 'root',
    [int]    $ServerPort = 22,
    [string] $KeyPath,
    [string] $PhpbbPath = '/var/www/html',
    [switch] $NoHostKeyCheck,
    [switch] $SkipCode,
    [switch] $SkipDatabase,
    [switch] $DryRun,
    [switch] $AutoConfirm,
    [string] $LogPath,
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

function Assert-LocalTool {
    param([string] $Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        Fail "Required local tool is not available in PATH: $Name"
    }
}

if ($Help) {
    Get-Help $MyInvocation.MyCommand.Path -Detailed
    exit 0
}

if ([string]::IsNullOrWhiteSpace($ServerHost)) {
    $ServerHost = Read-Host 'Enter server address (hostname or IP)'
}

if ([string]::IsNullOrWhiteSpace($ServerHost)) {
    Fail 'ServerHost is required.'
}

if ($SkipCode -and $SkipDatabase) {
    Fail 'Cannot use -SkipCode and -SkipDatabase together.'
}

Assert-LocalTool 'ssh'
Assert-LocalTool 'scp'

if (-not $SkipCode) {
    Assert-LocalTool 'tar'
}

if ($KeyPath) {
    $resolvedKey = Resolve-Path -LiteralPath $KeyPath -ErrorAction SilentlyContinue

    if (-not $resolvedKey) {
        Fail "SSH key not found: $KeyPath"
    }

    $KeyPath = $resolvedKey.Path
}

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'

if (-not $LogPath) {
    $logsFolder = Join-Path $PSScriptRoot 'logs'
    New-Item -ItemType Directory -Path $logsFolder -Force | Out-Null
    $LogPath = Join-Path $logsFolder "pull-live_$timestamp.log"
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
    $hostOptions = @('-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null')
    $sshArgs += $hostOptions
    $scpArgs += $hostOptions
}

$localPhpbbDir = Join-Path $PSScriptRoot 'phpbb'
$localDbFile = Join-Path $PSScriptRoot 'db_init\001_phpbb_backup.sql'

$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) "phpbb_pull_$timestamp"
$tempExtract = Join-Path $tempRoot 'phpbb'
$tempDb = Join-Path $tempRoot 'phpbb_db.sql'
$tempTar = Join-Path $tempRoot 'phpbb.tar.gz'
$localHelper = Join-Path $tempRoot 'pull-helper.sh'

$remoteHelper = "/tmp/phpbb-pull-$timestamp.sh"
$remoteDump = "/tmp/phpbb-pull-$timestamp.sql"
$remoteTar = "/tmp/phpbb-pull-$timestamp.tar.gz"

$helper = @'
#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

PHPBB_PATH=''
DUMP_PATH=''
TAR_PATH=''
DO_DB=0
DO_CODE=0
DRY_RUN=0

while [ "$#" -gt 0 ]; do
    case "$1" in
        --phpbb-path) PHPBB_PATH="$2"; shift 2 ;;
        --dump-path) DUMP_PATH="$2"; shift 2 ;;
        --tar-path) TAR_PATH="$2"; shift 2 ;;
        --database) DO_DB=1; shift ;;
        --code) DO_CODE=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        *) echo "ERROR: Unknown argument: $1" >&2; exit 2 ;;
    esac
done

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

[ -n "$PHPBB_PATH" ] || fail 'phpBB path was not supplied.'
[ -d "$PHPBB_PATH" ] || fail "phpBB directory does not exist: $PHPBB_PATH"
[ -f "$PHPBB_PATH/config.php" ] || fail "phpBB config.php does not exist: $PHPBB_PATH/config.php"
[ -f "$PHPBB_PATH/index.php" ] || fail "phpBB index.php does not exist: $PHPBB_PATH/index.php"
[ -f "$PHPBB_PATH/includes/constants.php" ] || fail 'phpBB constants.php is missing.'

command -v php >/dev/null 2>&1 || fail 'php is not installed on the remote host.'

if [ "$DO_DB" -eq 1 ]; then
    command -v mysqldump >/dev/null 2>&1 || fail 'mysqldump is not installed on the remote host.'
fi

if [ "$DO_CODE" -eq 1 ]; then
    command -v tar >/dev/null 2>&1 || fail 'tar is not installed on the remote host.'
fi

STAGING=$(mktemp -d /tmp/phpbb-pull-helper.XXXXXX)
MYSQL_CNF="$STAGING/mysql.cnf"
DB_NAME_FILE="$STAGING/dbname"
DB_META_FILE="$STAGING/dbmeta"
PHP_HELPER="$STAGING/read-config.php"

cleanup() {
    rm -rf "$STAGING"
}
trap cleanup EXIT

cat > "$PHP_HELPER" <<'PHP'
<?php
if ($argc < 5) {
    fwrite(STDERR, "Invalid helper invocation\n");
    exit(2);
}

$configPath = $argv[1];
$cnfPath = $argv[2];
$dbNamePath = $argv[3];
$metaPath = $argv[4];

include $configPath;

if (!isset($dbname) || $dbname === '' || !isset($dbuser) || $dbuser === '') {
    fwrite(STDERR, "Database name/user missing from config.php\n");
    exit(3);
}

$host = isset($dbhost) && $dbhost !== '' ? $dbhost : 'localhost';
$port = isset($dbport) ? (string) $dbport : '';
$password = isset($dbpasswd) ? (string) $dbpasswd : '';

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
file_put_contents($metaPath, "Database: " . $dbname . "\nHost: " . $host . "\nUser: " . $dbuser . "\n");
PHP

php "$PHP_HELPER" "$PHPBB_PATH/config.php" "$MYSQL_CNF" "$DB_NAME_FILE" "$DB_META_FILE"
chmod 600 "$MYSQL_CNF"

DB_NAME=$(cat "$DB_NAME_FILE")

PHPBB_VERSION=$(
    php -r 'define("IN_PHPBB", true); $table_prefix = "phpbb_"; include $argv[1]; echo PHPBB_VERSION;' \
        "$PHPBB_PATH/includes/constants.php"
)

printf 'Remote phpBB: %s\n' "$PHPBB_VERSION"
cat "$DB_META_FILE"

if [ "$DO_DB" -eq 1 ]; then
    if mysql --help >/dev/null 2>&1; then
        command -v mysql >/dev/null 2>&1 || true
    fi

    # Probe database access without exposing the password on the process command line.
    if command -v mysql >/dev/null 2>&1; then
        mysql --defaults-extra-file="$MYSQL_CNF" -N -e 'SELECT 1' "$DB_NAME" >/dev/null
        printf 'Database connectivity: OK\n'
    else
        printf 'Database connectivity: mysqldump-only host; continuing\n'
    fi

    if [ "$DRY_RUN" -eq 0 ]; then
        [ -n "$DUMP_PATH" ] || fail 'Dump output path was not supplied.'

        if mysqldump --help 2>&1 | grep -q -- '--no-tablespaces'; then
            mysqldump --defaults-extra-file="$MYSQL_CNF" --no-tablespaces "$DB_NAME" > "$DUMP_PATH"
            printf 'mysqldump: --no-tablespaces supported\n'
        else
            mysqldump --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" > "$DUMP_PATH"
            printf 'mysqldump: standard options used (--no-tablespaces unavailable)\n'
        fi

        test -s "$DUMP_PATH" || fail 'Database dump is empty.'
        printf 'Database dump: %s bytes\n' "$(wc -c < "$DUMP_PATH")"
    else
        # A small dump is a stronger compatibility probe than --help alone.
        PROBE="$STAGING/probe.sql"

        if mysqldump --help 2>&1 | grep -q -- '--no-tablespaces'; then
            mysqldump --defaults-extra-file="$MYSQL_CNF" --no-tablespaces \
                --no-data --skip-triggers "$DB_NAME" > "$PROBE"
        else
            mysqldump --defaults-extra-file="$MYSQL_CNF" \
                --no-data --skip-triggers "$DB_NAME" > "$PROBE"
        fi

        test -s "$PROBE" || fail 'mysqldump compatibility probe produced an empty file.'
        printf 'Database dump probe: OK\n'
    fi
fi

if [ "$DO_CODE" -eq 1 ]; then
    if [ "$DRY_RUN" -eq 0 ]; then
        [ -n "$TAR_PATH" ] || fail 'Tar output path was not supplied.'
        tar -C "$PHPBB_PATH" -czf "$TAR_PATH" .
        test -s "$TAR_PATH" || fail 'phpBB tarball is empty.'
        tar -tzf "$TAR_PATH" >/dev/null
        printf 'phpBB tarball: %s bytes\n' "$(wc -c < "$TAR_PATH")"
    else
        tar -C "$PHPBB_PATH" -cf - ./index.php ./config.php ./includes/constants.php >/dev/null
        printf 'Filesystem/tar probe: OK\n'
    fi
fi

if [ "$DRY_RUN" -eq 1 ]; then
    printf 'DRY RUN PASSED\n'
else
    printf 'REMOTE STAGING COMPLETE\n'
fi
'@

New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
Write-Utf8NoBomLf -Path $localHelper -Content $helper

# Replacing a bind-mounted phpbb/ tree while the local web container is running
# is needlessly risky. Database-only pulls are safe because the init dump is not
# consumed until a fresh DB volume is created.
if (-not $DryRun -and -not $SkipCode -and (Get-Command docker -ErrorAction SilentlyContinue)) {
    $localPhpbbRunning = & docker inspect -f '{{.State.Running}}' phpbb 2>$null

    if ($LASTEXITCODE -eq 0 -and ($localPhpbbRunning | Out-String).Trim() -eq 'true') {
        Fail "Local container 'phpbb' is running. Run 'docker compose down' before a real code pull."
    }
}

Write-Host ""
Write-Host "=== Pull Live phpBB Site ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Server:       $serverConnection (port $ServerPort)"
Write-Host "phpBB path:   $PhpbbPath"
Write-Host "Pull code:    $(-not $SkipCode)"
Write-Host "Pull DB:      $(-not $SkipDatabase)"
Write-Host "Local target: $PSScriptRoot"

if ($DryRun) {
    Write-Host "Mode:         DRY RUN (production/local content will not be replaced)" -ForegroundColor Yellow
}

Write-Host ""

$remoteCopied = $false

try {
    Write-Host "→ Testing SSH connection..."
    & ssh @sshArgs $serverConnection 'echo ok' | Out-Null

    if ($LASTEXITCODE -ne 0) {
        Fail "SSH connection failed for $serverConnection."
    }

    Write-Host "✅ SSH connection successful"

    Write-Host "→ Copying temporary read-only pull helper..."
    & scp @scpArgs $localHelper "$serverConnection`:$remoteHelper"

    if ($LASTEXITCODE -ne 0) {
        Fail 'Could not copy the temporary helper to the remote host.'
    }

    $remoteCopied = $true

    $helperArgs = @(
        $remoteHelper,
        '--phpbb-path', $PhpbbPath
    )

    if (-not $SkipDatabase) {
        $helperArgs += @('--database', '--dump-path', $remoteDump)
    }

    if (-not $SkipCode) {
        $helperArgs += @('--code', '--tar-path', $remoteTar)
    }

    if ($DryRun) {
        $helperArgs += '--dry-run'
    }

    $remoteCommand = ($helperArgs | ForEach-Object { ConvertTo-PosixQuoted $_ }) -join ' '

    Write-Host "→ Running remote pre-flight..."
    & ssh @sshArgs $serverConnection "bash $remoteCommand" | Out-Host

    if ($LASTEXITCODE -ne 0) {
        Fail 'Remote pull helper failed.'
    }

    if ($DryRun) {
        Write-Host ""
        Write-Host "✅ PULL DRY RUN PASSED" -ForegroundColor Green
        Write-Host "No production application/database data or local phpBB/database files were modified."
        exit 0
    }

    if (-not $AutoConfirm) {
        Write-Host ""
        Write-Host "WARNING: The requested production artifacts are staged remotely and ready to download." -ForegroundColor Yellow

        if (-not $SkipCode) {
            Write-Host "  - local phpbb/ will be replaced"
        }

        if (-not $SkipDatabase) {
            Write-Host "  - local db_init/001_phpbb_backup.sql will be replaced"
        }

        Write-Host ""
        $confirm = Read-Host "Type exactly 'PULL' to continue"

        if ($confirm -cne 'PULL') {
            Write-Host 'Pull cancelled. Local files were not changed.'
            exit 0
        }
    }

    if (-not $SkipDatabase) {
        Write-Host "→ Downloading staged database dump..."
        & scp @scpArgs "$serverConnection`:$remoteDump" $tempDb

        if ($LASTEXITCODE -ne 0) {
            Fail 'Could not download the database dump.'
        }

        if (-not (Test-Path $tempDb) -or (Get-Item $tempDb).Length -eq 0) {
            Fail 'Downloaded database dump is missing or empty.'
        }

        Write-Host "✅ Database staged locally"
    }

    if (-not $SkipCode) {
        Write-Host "→ Downloading staged phpBB tarball..."
        & scp @scpArgs "$serverConnection`:$remoteTar" $tempTar

        if ($LASTEXITCODE -ne 0) {
            Fail 'Could not download the phpBB tarball.'
        }

        if (-not (Test-Path $tempTar) -or (Get-Item $tempTar).Length -eq 0) {
            Fail 'Downloaded phpBB tarball is missing or empty.'
        }

        New-Item -ItemType Directory -Path $tempExtract -Force | Out-Null

        Write-Host "→ Extracting and validating phpBB locally..."
        & tar -xzf $tempTar -C $tempExtract

        if ($LASTEXITCODE -ne 0) {
            Fail 'Could not extract the downloaded phpBB tarball.'
        }

        foreach ($required in @('index.php', 'config.php', 'includes\constants.php')) {
            if (-not (Test-Path (Join-Path $tempExtract $required) -PathType Leaf)) {
                Fail "Downloaded phpBB tree is missing required file: $required"
            }
        }

        Write-Host "✅ phpBB filesystem staged locally"
    }

    # All requested artifacts are now downloaded and validated. Only now modify
    # the local workspace.
    if (-not $SkipDatabase) {
        $dbInitDir = Split-Path -Parent $localDbFile
        New-Item -ItemType Directory -Path $dbInitDir -Force | Out-Null

        $dbReplacement = "$localDbFile.new-$timestamp"
        $dbPrevious = "$localDbFile.before-pull-$timestamp"

        Copy-Item -LiteralPath $tempDb -Destination $dbReplacement -Force

        try {
            if (Test-Path $localDbFile) {
                Move-Item -LiteralPath $localDbFile -Destination $dbPrevious
            }

            Move-Item -LiteralPath $dbReplacement -Destination $localDbFile

            if (-not (Test-Path $localDbFile) -or (Get-Item $localDbFile).Length -eq 0) {
                Fail 'Local database replacement failed validation.'
            }

            if (Test-Path $dbPrevious) {
                Remove-Item -LiteralPath $dbPrevious -Force
            }
        }
        catch {
            Remove-Item -LiteralPath $dbReplacement -Force -ErrorAction SilentlyContinue

            if (Test-Path $localDbFile) {
                Remove-Item -LiteralPath $localDbFile -Force -ErrorAction SilentlyContinue
            }

            if (Test-Path $dbPrevious) {
                Move-Item -LiteralPath $dbPrevious -Destination $localDbFile
            }

            throw
        }

        $dbSizeMB = [math]::Round((Get-Item $localDbFile).Length / 1MB, 1)
        Write-Host "✅ Database replaced: db_init/001_phpbb_backup.sql ($dbSizeMB MB)"
    }

    if (-not $SkipCode) {
        $oldPhpbb = Join-Path $PSScriptRoot "phpbb.before-pull-$timestamp"

        try {
            if (Test-Path $localPhpbbDir) {
                Move-Item -LiteralPath $localPhpbbDir -Destination $oldPhpbb
            }

            Move-Item -LiteralPath $tempExtract -Destination $localPhpbbDir

            if (-not (Test-Path (Join-Path $localPhpbbDir 'index.php'))) {
                Fail 'Local phpBB replacement failed validation.'
            }

            if (Test-Path $oldPhpbb) {
                Remove-Item -LiteralPath $oldPhpbb -Recurse -Force
            }
        }
        catch {
            if (Test-Path $localPhpbbDir) {
                Remove-Item -LiteralPath $localPhpbbDir -Recurse -Force -ErrorAction SilentlyContinue
            }

            if (Test-Path $oldPhpbb) {
                Move-Item -LiteralPath $oldPhpbb -Destination $localPhpbbDir
            }

            throw
        }

        $codeBytes = (
            Get-ChildItem -LiteralPath $localPhpbbDir -Recurse -File |
            Measure-Object -Property Length -Sum
        ).Sum

        $codeSizeMB = [math]::Round($codeBytes / 1MB, 1)
        Write-Host "✅ phpBB code replaced: phpbb/ ($codeSizeMB MB)"
    }

    Write-Host ""
    Write-Host "════════════════════════════════════════════════" -ForegroundColor Green
    Write-Host "✅ PULL COMPLETE" -ForegroundColor Green
    Write-Host "════════════════════════════════════════════════"
    Write-Host ""

    if (-not $SkipDatabase) {
        Write-Host 'Database: db_init/001_phpbb_backup.sql'
    }

    if (-not $SkipCode) {
        Write-Host 'Code:     phpbb/'
    }

    Write-Host ""
    Write-Host "Next:"
    Write-Host "  docker compose down -v"
    Write-Host "  docker compose up --build -d"
    Write-Host "  .\snapshot.ps1 'live_baseline'"
}
finally {
    if ($remoteCopied) {
        $cleanupTargets = @($remoteHelper)

        if (-not $SkipDatabase) {
            $cleanupTargets += $remoteDump
        }

        if (-not $SkipCode) {
            $cleanupTargets += $remoteTar
        }

        $cleanupCommand = 'rm -f ' + (($cleanupTargets | ForEach-Object { ConvertTo-PosixQuoted $_ }) -join ' ')
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

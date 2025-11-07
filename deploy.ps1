param(
    [string] $SnapshotFolder,
    [string] $ServerUser = "root",
    [string] $ServerHost,
    [string] $PhpbbPath = "/var/www/html",
    [int]    $ServerPort = 22,
    [switch] $AutoConfirm,
    [string] $KeyPath,
    [switch] $DryRun,
    [switch] $NoHostKeyCheck,
    [string] $LogPath,
    [switch] $NoRollback,
    [switch] $SkipDatabase,
    [switch] $SkipPreflightChecks,
    [switch] $Help
)

# deploy.ps1 - Deploy phpBB snapshot to live server

$ErrorActionPreference = "Stop"

# --- Help Documentation ---
if ($Help) {
    Write-Host @"
=== phpBB Snapshot Deployment Tool ===

SYNOPSIS
    Deploy a phpBB snapshot (database + files) to a live server via SSH.

USAGE
    ./deploy.ps1 [options]

PARAMETERS
    -SnapshotFolder <string>
        Name of the snapshot folder in backups/ to deploy.
        If not provided, you'll be prompted to select from available snapshots.

    -ServerHost <string> (required)
        Target server hostname or IP address.
        
    -ServerUser <string>
        SSH username (default: root)
        
    -ServerPort <int>
        SSH port (default: 22)
        
    -PhpbbPath <string>
        Absolute path to phpBB installation on server (default: /var/www/html)
        
    -KeyPath <string>
        Path to SSH private key file for authentication.
        
    -AutoConfirm
        Skip interactive confirmation prompts.
        
    -DryRun
        Go through the full deployment process and log all commands that would be executed,
        but don't actually make changes. Useful for seeing exactly what will happen.
        
    -SkipDatabase
        Deploy files only, skip database backup and import.
        Useful for configuration-only or static file updates.
        
    -SkipPreflightChecks
        Skip the pre-flight validation checks and proceed directly to deployment.
        Use with caution - only when you're confident the environment is correct.
        
    -NoHostKeyCheck
        Disable SSH host key checking (use for testing only).
        
    -NoRollback
        Disable automatic rollback on failure (use with caution).
        
    -LogPath <string>
        Custom path for deployment transcript log.
        Default: logs/deploy_YYYYMMDD_HHMM.log
        
    -Help
        Display this help message.

EXAMPLES
    # Interactive deployment with snapshot selection
    ./deploy.ps1 -ServerHost myserver.com -KeyPath ~/.ssh/id_rsa
    
    # Auto-confirm deployment of specific snapshot
    ./deploy.ps1 -ServerHost 192.168.1.100 -SnapshotFolder 20251107_0851_3.2.11_new_cufc_style -AutoConfirm
    
    # Pre-flight check (dry-run) before deployment
    ./deploy.ps1 -ServerHost myserver.com -DryRun
    
    # Deploy files only, skip database
    ./deploy.ps1 -ServerHost myserver.com -SkipDatabase -AutoConfirm
    
    # Deploy to non-standard path and port
    ./deploy.ps1 -ServerHost myserver.com -PhpbbPath /home/phpbb/public_html -ServerPort 2222

PRE-FLIGHT CHECKS
    The deployment performs comprehensive validation before making changes:
    - SSH connectivity and authentication
    - Remote tool availability (tar, mysql, mysqldump)
    - Database connectivity and credentials
    - phpBB directory existence and permissions
    - Write access to cache/, store/, files/ directories
    - Staging directory creation capability

    Use -SkipPreflightChecks to bypass these checks (not recommended).ROLLBACK
    On failure, automatic rollback restores the previous state using backups in:
        /root/phpbb_backup_YYYYMMDD_HHMM/
    
    Manual rollback:
        Database: mysql -u user -p dbname < /root/phpbb_backup_*/phpbb_db_backup.sql
        Files: tar -xzf /root/phpbb_backup_*/phpbb_files_backup.tgz -C /path/to/phpbb

"@ -ForegroundColor Cyan
    exit 0
}

# --- Logging setup ---
$timestamp = Get-Date -Format "yyyyMMdd_HHmm"
if (-not $LogPath -or [string]::IsNullOrWhiteSpace($LogPath)) {
    $logsDir = Join-Path $PSScriptRoot "logs"
    if (-not (Test-Path $logsDir)) { New-Item -ItemType Directory -Path $logsDir | Out-Null }
    $LogPath = Join-Path $logsDir "deploy_$timestamp.log"
}

try { Start-Transcript -Path $LogPath -Force | Out-Null } catch { }

function Write-Log {
    param([string]$Message, [ConsoleColor]$Color = [ConsoleColor]::White)
    Write-Host $Message -ForegroundColor $Color
}

function Get-PhpbbConfig {
    param([string]$Content)
    $result = @{}
    # Match: $varname = 'value'; (semicolon optional, allows comments after)
    $re = [regex]'^\$(\w+)\s*=\s*[''"]([^''"]*)[''"]\s*;?'
    foreach ($line in ($Content -split "`n")) {
        $m = $re.Match($line.Trim())
        if ($m.Success) {
            $result[$m.Groups[1].Value] = $m.Groups[2].Value
        }
    }
    return $result
}

# Build SSH/SCP argument lists (initialized; recomputed after validation below)
$serverConnection = "$ServerUser@$ServerHost"
$sshArgs = @()
if ($ServerPort) { $sshArgs += @('-p', $ServerPort) }
if ($KeyPath)   { $sshArgs += @('-i', $KeyPath) }
if ($NoHostKeyCheck) { $sshArgs += @('-o','StrictHostKeyChecking=no','-o','UserKnownHostsFile=/dev/null') }

function Invoke-SSH {
    param([string]$Command)
    if ($DryRun) {
        Write-Log "[DRY-RUN] ssh $($sshArgs -join ' ') $serverConnection -- $Command" ([ConsoleColor]::Yellow)
        return 0
    }
    & ssh @sshArgs $serverConnection $Command
    return $LASTEXITCODE
}

function Invoke-SCP {
    param([string]$LocalPath, [string]$RemotePath, [switch]$Recursive)
    $scpArgs = @()
    if ($Recursive) { $scpArgs += '-r' }
    if ($ServerPort) { $scpArgs += @('-P', $ServerPort) }
    if ($KeyPath)   { $scpArgs += @('-i', $KeyPath) }
    if ($NoHostKeyCheck) { $scpArgs += @('-o','StrictHostKeyChecking=no','-o','UserKnownHostsFile=/dev/null') }
    if ($DryRun) {
        Write-Log "[DRY-RUN] scp $($scpArgs -join ' ') $LocalPath ${serverConnection}:$RemotePath" ([ConsoleColor]::Yellow)
        return 0
    }
    & scp @scpArgs $LocalPath "$serverConnection`:$RemotePath"
    return $LASTEXITCODE
}

function Test-PreflightChecks {
    Write-Host "`n=== Pre-Flight Checks ===" -ForegroundColor Cyan
    $allPassed = $true
    
    # 1. Check local tools
    Write-Host "→ Checking local tools..." -ForegroundColor Cyan
    $requiredTools = @('ssh', 'scp', 'tar')
    foreach ($tool in $requiredTools) {
        if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
            Write-Host "  ❌ $tool not found in PATH" -ForegroundColor Red
            $allPassed = $false
        } else {
            Write-Host "  ✅ $tool available" -ForegroundColor Green
        }
    }
    
    # 2. Check remote tools
    Write-Host "→ Checking remote tools..." -ForegroundColor Cyan
    $remoteTools = @('tar', 'mysql', 'mysqldump', 'chown', 'chmod')
    foreach ($tool in $remoteTools) {
        $checkCmd = "command -v $tool >/dev/null 2>&1 && echo 'ok' || echo 'missing'"
        $result = & ssh @sshArgs $serverConnection $checkCmd 2>$null
        if ($result -match 'ok') {
            Write-Host "  ✅ $tool available on server" -ForegroundColor Green
        } else {
            Write-Host "  ❌ $tool not found on server" -ForegroundColor Red
            $allPassed = $false
        }
    }
    
    # 3. Check phpBB directory existence and basic structure
    Write-Host "→ Checking phpBB directory structure..." -ForegroundColor Cyan
    $dirCheckCmd = @(
        "if [ -d '$PhpbbPath' ]; then echo 'phpbb_ok'; else echo 'phpbb_missing'; fi",
        "if [ -d '$PhpbbPath/cache' ]; then echo 'cache_ok'; else echo 'cache_missing'; fi",
        "if [ -d '$PhpbbPath/store' ]; then echo 'store_ok'; else echo 'store_missing'; fi",
        "if [ -d '$PhpbbPath/files' ]; then echo 'files_ok'; else echo 'files_missing'; fi",
        "if [ -d '$PhpbbPath/includes' ]; then echo 'includes_ok'; else echo 'includes_missing'; fi"
    ) -join "; "
    
    $dirResults = & ssh @sshArgs $serverConnection $dirCheckCmd 2>$null
    foreach ($line in ($dirResults -split "`n")) {
        $line = $line.Trim()
        if ($line -match '_ok$') {
            $dirName = $line -replace '_ok$', ''
            Write-Host "  ✅ Directory $dirName exists" -ForegroundColor Green
        } elseif ($line -match '_missing$') {
            $dirName = $line -replace '_missing$', ''
            if ($dirName -eq 'phpbb') {
                Write-Host "  ❌ phpBB directory $PhpbbPath does not exist!" -ForegroundColor Red
                $allPassed = $false
            } else {
                Write-Host "  ⚠️  Directory $dirName missing (will be created)" -ForegroundColor Yellow
            }
        }
    }
    
    # 4. Check write permissions on critical directories
    Write-Host "→ Checking write permissions..." -ForegroundColor Cyan
    $permCheckCmd = @(
        "touch '$PhpbbPath/.deploy_test' 2>/dev/null && rm -f '$PhpbbPath/.deploy_test' && echo 'phpbb_writable' || echo 'phpbb_readonly'",
        "touch '$PhpbbPath/cache/.deploy_test' 2>/dev/null && rm -f '$PhpbbPath/cache/.deploy_test' && echo 'cache_writable' || echo 'cache_readonly'",
        "touch '$PhpbbPath/store/.deploy_test' 2>/dev/null && rm -f '$PhpbbPath/store/.deploy_test' && echo 'store_writable' || echo 'store_readonly'",
        "touch '$PhpbbPath/files/.deploy_test' 2>/dev/null && rm -f '$PhpbbPath/files/.deploy_test' && echo 'files_writable' || echo 'files_readonly'"
    ) -join "; "
    
    $permResults = & ssh @sshArgs $serverConnection $permCheckCmd 2>$null
    foreach ($line in ($permResults -split "`n")) {
        $line = $line.Trim()
        if ($line -match '_writable$') {
            $dirName = $line -replace '_writable$', ''
            Write-Host "  ✅ $dirName is writable" -ForegroundColor Green
        } elseif ($line -match '_readonly$') {
            $dirName = $line -replace '_readonly$', ''
            Write-Host "  ❌ $dirName is not writable!" -ForegroundColor Red
            $allPassed = $false
        }
    }
    
    # 5. Check ability to create staging directory
    Write-Host "→ Checking staging directory access..." -ForegroundColor Cyan
    $stagingTest = "/tmp/.phpbb_deploy_test_$$"
    $stagingCmd = "mkdir -p '$stagingTest' && rmdir '$stagingTest' && echo 'ok' || echo 'fail'"
    $stagingResult = & ssh @sshArgs $serverConnection $stagingCmd 2>$null
    if ($stagingResult -match 'ok') {
        Write-Host "  ✅ Can create staging directories in /tmp" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Cannot create staging directories in /tmp!" -ForegroundColor Red
        $allPassed = $false
    }
    
    # 6. Check backup directory access
    Write-Host "→ Checking backup directory access..." -ForegroundColor Cyan
    $backupTest = "/root/.phpbb_backup_test_$$"
    $backupCmd = "mkdir -p '$backupTest' && rmdir '$backupTest' && echo 'ok' || echo 'fail'"
    $backupResult = & ssh @sshArgs $serverConnection $backupCmd 2>$null
    if ($backupResult -match 'ok') {
        Write-Host "  ✅ Can create backup directories in /root" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Cannot create backup directories in /root!" -ForegroundColor Red
        $allPassed = $false
    }
    
    Write-Host ""
    if ($allPassed) {
        Write-Host "✅ All pre-flight checks passed!`n" -ForegroundColor Green
    } else {
        Write-Host "❌ Some pre-flight checks failed. Please fix the issues before deploying.`n" -ForegroundColor Red
        exit 1
    }
}

Write-Host "=== phpBB Snapshot Deployment to Live Server ===`n" -ForegroundColor Cyan

# Validate server connection info
if ([string]::IsNullOrWhiteSpace($ServerHost)) {
    $ServerHost = Read-Host "Enter server address (hostname or IP)"
    if ([string]::IsNullOrWhiteSpace($ServerHost)) {
        Write-Host "❌ Server address is required." -ForegroundColor Red
        exit 1
    }
}

# Recompute server connection and ssh args after validation in case ServerHost was prompted
$serverConnection = "$ServerUser@$ServerHost"
$sshArgs = @()
if ($ServerPort) { $sshArgs += @('-p', $ServerPort) }
if ($KeyPath)   { $sshArgs += @('-i', $KeyPath) }
if ($NoHostKeyCheck) { $sshArgs += @('-o','StrictHostKeyChecking=no','-o','UserKnownHostsFile=/dev/null') }

Write-Host "🌐 Target server: $serverConnection (port $ServerPort)" -ForegroundColor Green
if ($KeyPath) { Write-Host "🔐 Using SSH key: $KeyPath" -ForegroundColor Green }
if ($DryRun)  { Write-Host "🧪 Dry-run mode: no changes will be made" -ForegroundColor Yellow }
Write-Host "📁 Target path: $PhpbbPath`n" -ForegroundColor Green

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
    Write-Host "Available snapshots:" -ForegroundColor Yellow
    for ($i = 0; $i -lt $backupFolders.Count; $i++) {
        Write-Host "[$($i+1)] $($backupFolders[$i])"
    }
    $selection = Read-Host "`nSelect a snapshot by number (1-$($backupFolders.Count))"
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
$sqlFile = Join-Path $backupDir "phpbb_db.sql"
$filesDir = Join-Path $backupDir "phpbb_files"

Write-Host "✅ Selected snapshot: $SnapshotFolder`n" -ForegroundColor Green

# Verify snapshot contents
if (-not $SkipDatabase) {
    if (-not (Test-Path $sqlFile)) {
        Write-Host "❌ Database backup not found: $sqlFile" -ForegroundColor Red
        Write-Host "   Use -SkipDatabase if you only want to deploy files." -ForegroundColor Yellow
        exit 1
    }
}
if (-not (Test-Path $filesDir)) {
    Write-Host "❌ Files directory not found: $filesDir" -ForegroundColor Red
    exit 1
}

# Confirm deployment
if ($SkipDatabase) {
    Write-Host "⚠️  WARNING: This will:" -ForegroundColor Yellow
    Write-Host "   1. Backup the current live phpBB files" -ForegroundColor Yellow
    Write-Host "   2. Replace live phpBB files with: $SnapshotFolder" -ForegroundColor Yellow
    Write-Host "   (Database operations skipped)`n" -ForegroundColor Yellow
} else {
    Write-Host "⚠️  WARNING: This will:" -ForegroundColor Yellow
    Write-Host "   1. Backup the current live database" -ForegroundColor Yellow
    Write-Host "   2. Backup the current live phpBB files" -ForegroundColor Yellow
    Write-Host "   3. Replace live database with: $SnapshotFolder" -ForegroundColor Yellow
    Write-Host "   4. Replace live phpBB files with: $SnapshotFolder`n" -ForegroundColor Yellow
}

if (-not $AutoConfirm -and -not $DryRun) {
    $confirm = Read-Host "Are you sure you want to proceed? (yes/no)"
    if ($confirm -ne "yes") {
        Write-Host "❌ Deployment cancelled." -ForegroundColor Red
        exit 0
    }
} elseif ($AutoConfirm) {
    Write-Host "AutoConfirm enabled: proceeding without interactive confirmation" -ForegroundColor Yellow
}

Write-Host "`n🚀 Starting deployment...`n" -ForegroundColor Cyan

# Test SSH connection
Write-Host "→ Testing SSH connection..." -ForegroundColor Cyan
try {
    # Always perform a real connectivity test, even in DryRun (non-destructive)
    & ssh @sshArgs $serverConnection "echo ok" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "ssh exited with code $LASTEXITCODE" }
    Write-Host "✅ SSH connection successful`n" -ForegroundColor Green
} catch {
    Write-Host "❌ Cannot connect to server via SSH. Please check host, port, key/password, and firewall." -ForegroundColor Red
    Write-Host "Details: $($_.Exception.Message)" -ForegroundColor DarkGray
    exit 1
}

# Run pre-flight checks (unless skipped)
if (-not $SkipPreflightChecks) {
    Write-Host "════════════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host "PRE-FLIGHT CHECKS" -ForegroundColor Cyan
    Write-Host "════════════════════════════════════════════════" -ForegroundColor Cyan
    Test-PreflightChecks
    Write-Host "✅ All pre-flight checks passed`n" -ForegroundColor Green
} else {
    Write-Host "⚠️  Skipping pre-flight checks (as requested)`n" -ForegroundColor Yellow
}

# Get live database credentials (skip if not doing database deployment)
if (-not $SkipDatabase) {
    Write-Host "→ Retrieving live database credentials (remote config.php)..." -ForegroundColor Cyan
    # Always fetch the live server's config.php even in DryRun (safe, read-only)
    $sshCmd = "ssh"
    $sshCmdArgs = $sshArgs + @($serverConnection, "cat $PhpbbPath/config.php")
    Write-Host "→ DEBUG: Running: ssh $($sshCmdArgs -join ' ')" -ForegroundColor DarkGray
    $configContent = & $sshCmd @sshCmdArgs 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($configContent)) {
        Write-Host "❌ Could not read config.php from server. Exit code: $LASTEXITCODE" -ForegroundColor Red
        Write-Host "Output: $configContent" -ForegroundColor Red
        Write-Host "ℹ️  If this is an unusual layout, re-run with -PhpbbPath to specify the correct path." -ForegroundColor Yellow
        exit 1
    }

    # Parse database credentials (robust against spacing/quotes)
    $config = Get-PhpbbConfig $configContent
    Write-Host "→ DEBUG: Config content length: $($configContent.Length) bytes" -ForegroundColor DarkGray
    Write-Host "→ DEBUG: Config keys found: $($config.Keys -join ', ')" -ForegroundColor DarkGray

    $rawDbHost = $config['dbhost']
    $dbhost   = $rawDbHost; if (-not $dbhost) { $dbhost = 'localhost' }
    if ([string]::IsNullOrWhiteSpace($rawDbHost)) {
        Write-Host "→ dbhost empty in config.php; assuming localhost" -ForegroundColor DarkYellow
    }

    $dbname   = $config['dbname']
    $dbuser   = $config['dbuser']
    $dbpasswd = $config['dbpasswd']

    Write-Host "→ Parsed: host=$dbhost (raw='$rawDbHost') db=$dbname user=$dbuser pass=$([string]::IsNullOrWhiteSpace($dbpasswd) ? '<empty>' : '****')" -ForegroundColor DarkGray

    if ([string]::IsNullOrWhiteSpace($dbname) -or [string]::IsNullOrWhiteSpace($dbuser)) {
        Write-Host "❌ Could not parse required database credentials from config.php" -ForegroundColor Red
        Write-Host "Please verify the file and permissions." -ForegroundColor Red
        exit 1
    }

    Write-Host "✅ Database: $dbname (user: $dbuser, host: $dbhost)`n" -ForegroundColor Green

    # MySQL connectivity test (non-destructive)
    Write-Host "→ Testing MySQL connectivity..." -ForegroundColor Cyan
    $testMysql = "mysql -h $dbhost -u $dbuser"
    if (-not [string]::IsNullOrWhiteSpace($dbpasswd)) { $testMysql += " -p'$dbpasswd'" }
    $testMysql += " -e 'SELECT 1' $dbname"
    & ssh @sshArgs $serverConnection $testMysql | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Unable to connect to MySQL with credentials from config.php" -ForegroundColor Red
        Write-Host "   Host: $dbhost, DB: $dbname, User: $dbuser" -ForegroundColor Yellow
        Write-Host "   Tip: Ensure MySQL is reachable on the host and credentials are current." -ForegroundColor Yellow
        exit 1
    }
    Write-Host "✅ MySQL connectivity OK`n" -ForegroundColor Green
} else {
    Write-Host "⏭️  Skipping database credential retrieval (files-only deployment)`n" -ForegroundColor Yellow
}

# Create backup directory on server
$serverBackupDir = "/root/phpbb_backup_$timestamp"
Write-Host "→ Creating backup directory on server: $serverBackupDir" -ForegroundColor Cyan
if ((Invoke-SSH "mkdir -p $serverBackupDir") -ne 0) { Write-Host "❌ Failed to create backup directory" -ForegroundColor Red; exit 1 }
Write-Host "✅ Backup directory created`n" -ForegroundColor Green

# Backup live database (skip if files-only deployment)
if (-not $SkipDatabase) {
    Write-Host "→ Backing up live database..." -ForegroundColor Cyan
    # Use --no-tablespaces to suppress PROCESS privilege requirement (not needed for logical backup)
    $mysqlCmd = "mysqldump --no-tablespaces -h $dbhost -u $dbuser"
    if (-not [string]::IsNullOrWhiteSpace($dbpasswd)) {
        # Avoid single-quote wrapping which can be misinterpreted remotely; mysql client accepts -ppassword
        $mysqlCmd += " -p$dbpasswd"
    }
    $mysqlCmd += " $dbname > $serverBackupDir/phpbb_db_backup.sql"

    if ((Invoke-SSH $mysqlCmd) -ne 0) {
        Write-Host "❌ Database backup failed. Aborting deployment." -ForegroundColor Red
        exit 1
    }
    Write-Host "✅ Live database backed up to: $serverBackupDir/phpbb_db_backup.sql`n" -ForegroundColor Green
} else {
    Write-Host "⏭️  Skipping database backup (files-only deployment)`n" -ForegroundColor Yellow
}

# Backup live phpBB files
Write-Host "→ Backing up live phpBB files..." -ForegroundColor Cyan
if ((Invoke-SSH "if command -v tar >/dev/null 2>&1; then tar -C `$(dirname $PhpbbPath) -czf $serverBackupDir/phpbb_files_backup.tgz `$(basename $PhpbbPath); else cp -r $PhpbbPath $serverBackupDir/phpbb_files_backup; fi") -ne 0) {
    Write-Host "❌ Files backup failed. Aborting deployment." -ForegroundColor Red
    exit 1
}
Write-Host "✅ Live files backed up to: $serverBackupDir (tarball if available)`n" -ForegroundColor Green

# Upload and import new database (skip if files-only deployment)
if (-not $SkipDatabase) {
    # Upload new database
    Write-Host "→ Uploading new database to server..." -ForegroundColor Cyan
    if ((Invoke-SCP $sqlFile "/tmp/phpbb_deploy.sql") -ne 0) {
        Write-Host "❌ Database upload failed. Your live site is still intact." -ForegroundColor Red
        exit 1
    }
    Write-Host "✅ Database uploaded`n" -ForegroundColor Green

    # Import new database
    Write-Host "→ Importing new database..." -ForegroundColor Cyan
    # Filter out MariaDB-specific commands that MySQL 5.6 doesn't understand
    # Then pipe to mysql (safer than trying to escape in single command)
    $importCmd = "cat /tmp/phpbb_deploy.sql | grep -v '^/\*M!' | mysql -h $dbhost -u $dbuser"
    if (-not [string]::IsNullOrWhiteSpace($dbpasswd)) {
        $importCmd += " -p$dbpasswd"
    }
    $importCmd += " $dbname"

    if ((Invoke-SSH $importCmd) -ne 0) {
        Write-Host "❌ Database import failed!" -ForegroundColor Red
        Write-Host "⚠️  Your backup is at: $serverBackupDir" -ForegroundColor Yellow
        Write-Host "⚠️  To restore: mysql -u $dbuser -p $dbname < $serverBackupDir/phpbb_db_backup.sql" -ForegroundColor Yellow
        if (-not $NoRollback) {
            Write-Host "↩️  Rolling back database..." -ForegroundColor Yellow
            $rollbackPwd = ""
            if ($dbpasswd) { $rollbackPwd = " -p$dbpasswd" }
            [void](Invoke-SSH "mysql -h $dbhost -u $dbuser$rollbackPwd $dbname < $serverBackupDir/phpbb_db_backup.sql")
        }
        exit 1
    }
    Write-Host "✅ Database imported successfully`n" -ForegroundColor Green
} else {
    Write-Host "⏭️  Skipping database upload and import (files-only deployment)`n" -ForegroundColor Yellow
}

# Upload phpBB files
Write-Host "→ Uploading phpBB files to server..." -ForegroundColor Cyan
Write-Host "   (This may take a while for large file sets...)" -ForegroundColor Gray

# Ensure staging directory exists on the server (avoid escaping that prevents expansion)
$stagingDir = "/tmp/phpbb_deploy_$timestamp"
if ((Invoke-SSH "mkdir -p $stagingDir") -ne 0) {
    Write-Host "❌ Staging directory creation failed" -ForegroundColor Red
    exit 1
}

# Package files into a tarball locally for reliable transfer
$localTar = Join-Path $env:TEMP "phpbb_files_$timestamp.tar.gz"
try {
    if (Test-Path $localTar) { Remove-Item $localTar -Force }
    # Create tar from the snapshot files directory
    Write-Host "→ Creating local tarball of phpBB files..." -ForegroundColor Cyan
    if (-not $DryRun) {
        & tar -C $filesDir -czf $localTar .
        if ($LASTEXITCODE -ne 0) { throw "Tar creation failed" }
    } else {
        Write-Host "[DRY-RUN] tar -C $filesDir -czf $localTar ." -ForegroundColor Yellow
    }
    Write-Host "✅ Local tarball created: $localTar" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Could not create tarball. Falling back to recursive SCP." -ForegroundColor Yellow
    if ((Invoke-SSH "mkdir -p /tmp/phpbb_deploy_$timestamp/files") -ne 0 -and -not $DryRun) { Write-Host "❌ Staging directory creation failed" -ForegroundColor Red; exit 1 }
    if ((Invoke-SCP "$filesDir/*" "/tmp/phpbb_deploy_$timestamp/files/" -Recursive) -ne 0) {
        Write-Host "❌ File upload failed!" -ForegroundColor Red
        exit 1
    }
}

if (Test-Path $localTar) {
    if ((Invoke-SCP $localTar "/tmp/phpbb_deploy_$timestamp/phpbb_files.tar.gz") -ne 0) { Write-Host "❌ Tar upload failed" -ForegroundColor Red; exit 1 }
    if ((Invoke-SSH "mkdir -p /tmp/phpbb_deploy_$timestamp/files && tar -xzf /tmp/phpbb_deploy_$timestamp/phpbb_files.tar.gz -C /tmp/phpbb_deploy_$timestamp/files") -ne 0) {
        Write-Host "❌ Remote extract failed" -ForegroundColor Red
        exit 1
    }
}

Write-Host "✅ Files staged on server`n" -ForegroundColor Green

# Replace live files (safer: overlay copy via tar stream, no pre-wipe)
Write-Host "→ Replacing live phpBB files..." -ForegroundColor Cyan
$stagingDir = "/tmp/phpbb_deploy_$timestamp"
$replaceCmd = @(
    "set -e",
    "if [ -d $stagingDir/files ]; then true; else echo 'staging missing' >&2; exit 1; fi",
    # Basic sanity check to avoid blanking the site if staging is wrong
    "if [ ! -f $stagingDir/files/index.php ]; then echo 'staging does not contain index.php' >&2; exit 1; fi",
    # Overlay copy using tar stream to preserve perms and handle dotfiles reliably
    "tar -C $stagingDir/files -cf - . | tar -C $PhpbbPath -xf -",
    # Clear caches after overlay
    "rm -rf $PhpbbPath/cache/* $PhpbbPath/store/* 2>/dev/null || true",
    # Ownership and permissions
    "chown -R apache:apache $PhpbbPath 2>/dev/null || chown -R www-data:www-data $PhpbbPath 2>/dev/null || true",
    "chmod -R 755 $PhpbbPath",
    "chmod -R 777 $PhpbbPath/cache $PhpbbPath/store $PhpbbPath/files 2>/dev/null || true"
) -join " && "

$replaceResult = Invoke-SSH $replaceCmd
if ($replaceResult -ne 0) {
    Write-Host "❌ File replacement failed!" -ForegroundColor Red
    if (-not $NoRollback) {
        Write-Host "↩️  Rolling back files..." -ForegroundColor Yellow
    [void](Invoke-SSH "rm -rf $PhpbbPath/*; if [ -f $serverBackupDir/phpbb_files_backup.tgz ]; then tar -C `$(dirname $PhpbbPath) -xzf $serverBackupDir/phpbb_files_backup.tgz; else cp -r $serverBackupDir/phpbb_files_backup/* $PhpbbPath/ 2>/dev/null || cp -r $serverBackupDir/phpbb_files_backup $PhpbbPath; fi")
    }
    exit 1
}
Write-Host "✅ Files replaced and permissions set`n" -ForegroundColor Green

# Cleanup
Write-Host "→ Cleaning up temporary files..." -ForegroundColor Cyan
$cleanupCmd = "rm -rf $stagingDir"
if (-not $SkipDatabase) {
    $cleanupCmd = "rm -f /tmp/phpbb_deploy.sql; " + $cleanupCmd
}
if ((Invoke-SSH $cleanupCmd) -ne 0) { Write-Host "⚠️  Cleanup reported warnings" -ForegroundColor Yellow }
# Remove local tar if created
if (Test-Path $localTar) { Remove-Item $localTar -Force }
Write-Host "✅ Cleanup complete`n" -ForegroundColor Green

# Summary
Write-Host "════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "✅ DEPLOYMENT SUCCESSFUL!" -ForegroundColor Green
Write-Host "════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""
Write-Host "Deployed snapshot: $SnapshotFolder" -ForegroundColor Cyan
Write-Host "Target server: $serverConnection" -ForegroundColor Cyan
Write-Host "Live backup location: $serverBackupDir" -ForegroundColor Cyan
Write-Host "Log file: $LogPath" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Test your live site thoroughly" -ForegroundColor White
Write-Host "2. Check phpBB ACP for any issues" -ForegroundColor White
Write-Host "3. Clear phpBB cache if needed" -ForegroundColor White
Write-Host ""
Write-Host "If you need to rollback:" -ForegroundColor Yellow
Write-Host "  Database: mysql -u $dbuser -p $dbname < $serverBackupDir/phpbb_db_backup.sql" -ForegroundColor White
Write-Host "  Files: cp -r $serverBackupDir/phpbb_files_backup/* $PhpbbPath/" -ForegroundColor White
Write-Host ""

try { Stop-Transcript | Out-Null } catch { }

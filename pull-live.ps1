param(
    [string]$ServerHost,
    [string]$ServerUser = "root",
    [int]$ServerPort = 22,
    [string]$KeyPath,
    [string]$PhpbbPath = "/var/www/html",
    [switch]$NoHostKeyCheck,
    [switch]$SkipCode,
    [switch]$SkipDatabase,
    [switch]$Help
)

# pull-live.ps1 - Pull live phpBB code and database to local Docker environment

$ErrorActionPreference = "Stop"

# --- Help Documentation ---
if ($Help) {
    Write-Host @"
=== Pull Live phpBB Site ===

SYNOPSIS
    Download the latest code and database from your live phpBB server
    to your local Docker development environment.

USAGE
    ./pull-live.ps1 [options]

PARAMETERS
    -ServerHost <string> (required)
        Live server hostname or IP address.
        
    -ServerUser <string>
        SSH username (default: root)
        
    -ServerPort <int>
        SSH port (default: 22)
        
    -PhpbbPath <string>
        Absolute path to phpBB installation on server (default: /var/www/html)
        
    -KeyPath <string>
        Path to SSH private key file for authentication.
        
    -SkipCode
        Skip downloading phpBB code files (database only).
        
    -SkipDatabase
        Skip downloading database export (code only).
        
    -NoHostKeyCheck
        Disable SSH host key checking (use for testing only).
        
    -Help
        Display this help message.

EXAMPLES
    # Pull both code and database
    ./pull-live.ps1 -ServerHost myserver.com -KeyPath ~/.ssh/id_rsa
    
    # Pull database only
    ./pull-live.ps1 -ServerHost myserver.com -SkipCode
    
    # Pull from non-standard path
    ./pull-live.ps1 -ServerHost myserver.com -PhpbbPath /home/phpbb/public_html

NOTES
    - This will overwrite your local phpbb/ folder and db_init/001_phpbb_backup.sql
    - After pulling, rebuild your Docker containers with: docker compose up --build -d
    - The script preserves your local config/docker.config.php for database connection

"@ -ForegroundColor Cyan
    exit 0
}

Write-Host "`n=== Pull Live phpBB Site to Local Environment ===`n" -ForegroundColor Cyan

# Validate required parameters
if ([string]::IsNullOrWhiteSpace($ServerHost)) {
    $ServerHost = Read-Host "Enter server address (hostname or IP)"
    if ([string]::IsNullOrWhiteSpace($ServerHost)) {
        Write-Host "❌ Server address is required." -ForegroundColor Red
        exit 1
    }
}

if ($SkipCode -and $SkipDatabase) {
    Write-Host "❌ Cannot skip both code and database. Nothing to do!" -ForegroundColor Red
    exit 1
}

# Build SSH/SCP arguments
$serverConnection = "$ServerUser@$ServerHost"
$sshArgs = @()
if ($ServerPort) { $sshArgs += @('-p', $ServerPort) }
if ($KeyPath)   { $sshArgs += @('-i', $KeyPath) }
if ($NoHostKeyCheck) { $sshArgs += @('-o','StrictHostKeyChecking=no','-o','UserKnownHostsFile=/dev/null') }

$scpArgs = @()
if ($ServerPort) { $scpArgs += @('-P', $ServerPort) }
if ($KeyPath)   { $scpArgs += @('-i', $KeyPath) }
if ($NoHostKeyCheck) { $scpArgs += @('-o','StrictHostKeyChecking=no','-o','UserKnownHostsFile=/dev/null') }

Write-Host "🌐 Live server: $serverConnection (port $ServerPort)" -ForegroundColor Green
if ($KeyPath) { Write-Host "🔐 Using SSH key: $KeyPath" -ForegroundColor Green }
Write-Host "📁 Remote path: $PhpbbPath`n" -ForegroundColor Green

# Test SSH connection
Write-Host "→ Testing SSH connection..." -ForegroundColor Cyan
try {
    & ssh @sshArgs $serverConnection "echo ok" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "ssh exited with code $LASTEXITCODE" }
    Write-Host "✅ SSH connection successful`n" -ForegroundColor Green
} catch {
    Write-Host "❌ Cannot connect to server via SSH." -ForegroundColor Red
    Write-Host "Details: $($_.Exception.Message)" -ForegroundColor DarkGray
    exit 1
}

# Confirm operation
Write-Host "⚠️  WARNING: This will overwrite your local environment:" -ForegroundColor Yellow
if (-not $SkipCode) {
    Write-Host "   • phpbb/ folder will be replaced with live code" -ForegroundColor Yellow
}
if (-not $SkipDatabase) {
    Write-Host "   • db_init/001_phpbb_backup.sql will be replaced with live database" -ForegroundColor Yellow
}
Write-Host ""
$confirm = Read-Host "Continue? (yes/no)"
if ($confirm -ne "yes") {
    Write-Host "❌ Operation cancelled." -ForegroundColor Red
    exit 0
}

Write-Host "`n🚀 Starting pull...`n" -ForegroundColor Cyan

$localPhpbbDir = Join-Path $PSScriptRoot "phpbb"
$localDbFile = Join-Path $PSScriptRoot "db_init\001_phpbb_backup.sql"

# Pull database
if (-not $SkipDatabase) {
    Write-Host "→ Retrieving database credentials from live server..." -ForegroundColor Cyan
    $configContent = & ssh @sshArgs $serverConnection "cat $PhpbbPath/config.php" 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($configContent)) {
        Write-Host "❌ Could not read config.php from server." -ForegroundColor Red
        exit 1
    }

    # Parse database credentials
    $config = @{}
    $re = [regex]'^\$(\w+)\s*=\s*[''"]([^''"]*)[''"]\s*;?'
    foreach ($line in ($configContent -split "`n")) {
        $m = $re.Match($line.Trim())
        if ($m.Success) {
            $config[$m.Groups[1].Value] = $m.Groups[2].Value
        }
    }

    $dbhost = $config['dbhost']; if (-not $dbhost) { $dbhost = 'localhost' }
    $dbname = $config['dbname']
    $dbuser = $config['dbuser']
    $dbpasswd = $config['dbpasswd']

    if ([string]::IsNullOrWhiteSpace($dbname) -or [string]::IsNullOrWhiteSpace($dbuser)) {
        Write-Host "❌ Could not parse database credentials from config.php" -ForegroundColor Red
        exit 1
    }

    Write-Host "✅ Database: $dbname (user: $dbuser, host: $dbhost)" -ForegroundColor Green

    Write-Host "→ Exporting live database..." -ForegroundColor Cyan
    
    # Create dump on the remote server first to avoid encoding issues through SSH
    $remoteDumpFile = "/tmp/phpbb_dump_$(Get-Date -Format 'yyyyMMddHHmmss').sql"
    $dumpCmd = "mysqldump --no-tablespaces -h $dbhost -u $dbuser"
    if (-not [string]::IsNullOrWhiteSpace($dbpasswd)) {
        $dumpCmd += " -p$dbpasswd"
    }
    $dumpCmd += " $dbname > $remoteDumpFile"
    
    Write-Host "   Creating dump on remote server..." -ForegroundColor Gray
    & ssh @sshArgs $serverConnection $dumpCmd
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Database export failed!" -ForegroundColor Red
        exit 1
    }
    
    Write-Host "   Downloading dump file..." -ForegroundColor Gray
    & scp @scpArgs "$serverConnection`:$remoteDumpFile" $localDbFile
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Failed to download database dump!" -ForegroundColor Red
        exit 1
    }
    
    # Clean up remote dump file
    & ssh @sshArgs $serverConnection "rm -f $remoteDumpFile"

    $dbSize = (Get-Item $localDbFile).Length / 1MB
    Write-Host "✅ Database exported to: db_init/001_phpbb_backup.sql ($([math]::Round($dbSize, 2)) MB)`n" -ForegroundColor Green
} else {
    Write-Host "⏭️  Skipping database export`n" -ForegroundColor Yellow
}

# Pull code
if (-not $SkipCode) {
    Write-Host "→ Downloading phpBB files from live server..." -ForegroundColor Cyan
    Write-Host "   (This may take a while for large installations...)" -ForegroundColor Gray

    # Create temporary directory for download
    $tempDir = Join-Path $env:TEMP "phpbb_pull_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
    New-Item -ItemType Directory -Path $tempDir | Out-Null

    try {
        # Create tarball on server and download it
        $remoteTar = "/tmp/phpbb_export_$(Get-Date -Format 'yyyyMMdd_HHmmss').tar.gz"
        Write-Host "   Creating remote tarball..." -ForegroundColor Gray
        $tarCmd = "tar -C `$(dirname $PhpbbPath) -czf $remoteTar `$(basename $PhpbbPath)"
        & ssh @sshArgs $serverConnection $tarCmd
        if ($LASTEXITCODE -ne 0) {
            throw "Failed to create remote tarball"
        }

        Write-Host "   Downloading tarball..." -ForegroundColor Gray
        $localTar = Join-Path $tempDir "phpbb.tar.gz"
        & scp @scpArgs "$serverConnection`:$remoteTar" $localTar
        if ($LASTEXITCODE -ne 0) {
            throw "Failed to download tarball"
        }

        # Clean up remote tarball
        & ssh @sshArgs $serverConnection "rm -f $remoteTar"

        # Remove old phpbb directory if it exists
        if (Test-Path $localPhpbbDir) {
            Write-Host "   Removing old phpbb/ directory..." -ForegroundColor Gray
            Remove-Item -Recurse -Force $localPhpbbDir
        }

        # Extract tarball
        Write-Host "   Extracting files..." -ForegroundColor Gray
        New-Item -ItemType Directory -Path $localPhpbbDir | Out-Null
        & tar -xzf $localTar -C $localPhpbbDir --strip-components=1
        if ($LASTEXITCODE -ne 0) {
            throw "Failed to extract tarball"
        }

        $codeSize = (Get-ChildItem $localPhpbbDir -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
        Write-Host "✅ Code downloaded to: phpbb/ ($([math]::Round($codeSize, 2)) MB)`n" -ForegroundColor Green

    } catch {
        Write-Host "❌ Code download failed: $($_.Exception.Message)" -ForegroundColor Red
        exit 1
    } finally {
        # Clean up temp directory
        if (Test-Path $tempDir) {
            Remove-Item -Recurse -Force $tempDir
        }
    }
} else {
    Write-Host "⏭️  Skipping code download`n" -ForegroundColor Yellow
}

# Summary
Write-Host "════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "✅ PULL COMPLETE!" -ForegroundColor Green
Write-Host "════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""
if (-not $SkipDatabase) {
    Write-Host "Database: db_init/001_phpbb_backup.sql" -ForegroundColor Cyan
}
if (-not $SkipCode) {
    Write-Host "Code: phpbb/" -ForegroundColor Cyan
}
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Rebuild Docker containers: docker compose up --build -d" -ForegroundColor White
Write-Host "2. Access your local site at: http://localhost:8080" -ForegroundColor White
Write-Host "3. Create a snapshot before making changes: .\snapshot.ps1 'live_baseline'" -ForegroundColor White
Write-Host ""

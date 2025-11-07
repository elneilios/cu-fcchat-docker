param(
    [string] $SnapshotFolder,
    [switch] $AutoConfirm,
    [switch] $DryRun,
    [switch] $SkipDatabase,
    [switch] $SkipPreflightChecks,
    [switch] $NoHostKeyCheck,
    [string] $KeyPath,
    [switch] $InjectKey
)

# Thin wrapper calling deploy.ps1 with container credentials

$ErrorActionPreference = "Stop"

Write-Host "=== Wrapper: deploy-test.ps1 -> deploy.ps1 (Docker container) ===" -ForegroundColor Cyan

# Defaults for test wrapper: enable host key bypass and key injection unless overridden
if (-not $PSBoundParameters.ContainsKey('NoHostKeyCheck')) { $NoHostKeyCheck = $true }
if (-not $KeyPath -and -not $PSBoundParameters.ContainsKey('InjectKey')) { $InjectKey = $true }

if ($InjectKey -and -not $KeyPath) {
    $keyDir = Join-Path $PSScriptRoot ".ssh"
    if (-not (Test-Path $keyDir)) { New-Item -ItemType Directory -Path $keyDir | Out-Null }
    $KeyPath = Join-Path $keyDir "docker_test_ed25519"
    $pubPath = "$KeyPath.pub"
    if (-not (Test-Path $KeyPath)) {
        Write-Host "Generating test SSH key..." -ForegroundColor Yellow
        ssh-keygen -q -t ed25519 -f $KeyPath -N "" | Out-Null
    }
    Write-Host "Injecting public key into container authorized_keys..." -ForegroundColor Yellow
    $pubKey = Get-Content -Raw $pubPath
    # Copy key into container (uses docker exec directly)
    docker exec phpbb /bin/bash -c "mkdir -p /root/.ssh && echo '$pubKey' >> /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys && chmod 700 /root/.ssh"
}

$deployScript = Join-Path $PSScriptRoot "deploy.ps1"
if (-not (Test-Path $deployScript)) {
    Write-Host "❌ deploy.ps1 not found. Run from repository root." -ForegroundColor Red
    exit 1
}

# Pass through to deploy.ps1 using local container SSH settings (prefer key auth if available)
# PhpbbPath omitted to use deploy.ps1 default (/var/www/html)
& $deployScript -SnapshotFolder $SnapshotFolder -ServerUser root -ServerHost localhost -ServerPort 2222 -AutoConfirm:$AutoConfirm -DryRun:$DryRun -SkipDatabase:$SkipDatabase -SkipPreflightChecks:$SkipPreflightChecks -NoHostKeyCheck:$NoHostKeyCheck -KeyPath $KeyPath

Write-Host "=== Test deployment finished ===" -ForegroundColor Green

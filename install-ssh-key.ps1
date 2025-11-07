param(
    [string]$ServerHost = "cu-fcchat.com",
    [string]$ServerUser = "root",
    [string]$KeyPath = "$HOME\.ssh\cu-fcchat-prod.pub"
)

# Install SSH public key on remote server
# This script will prompt for your password once, then set up key-based auth

$ErrorActionPreference = "Stop"

Write-Host "=== SSH Key Installation for $ServerUser@$ServerHost ===" -ForegroundColor Cyan
Write-Host ""

# Verify the public key exists
if (-not (Test-Path $KeyPath)) {
    Write-Host "❌ Public key not found: $KeyPath" -ForegroundColor Red
    exit 1
}

$publicKey = Get-Content -Raw $KeyPath
$publicKey = $publicKey.Trim()

Write-Host "Public key to install:" -ForegroundColor Yellow
Write-Host $publicKey -ForegroundColor White
Write-Host ""

Write-Host "This will:" -ForegroundColor Cyan
Write-Host "1. Connect to $ServerUser@$ServerHost (you'll need to enter your password)" -ForegroundColor White
Write-Host "2. Create ~/.ssh directory if it doesn't exist" -ForegroundColor White
Write-Host "3. Add your public key to ~/.ssh/authorized_keys" -ForegroundColor White
Write-Host "4. Set correct permissions" -ForegroundColor White
Write-Host ""

$confirm = Read-Host "Continue? (yes/no)"
if ($confirm -ne "yes") {
    Write-Host "Cancelled." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "→ Connecting to server (you'll be prompted for your password)..." -ForegroundColor Cyan
Write-Host ""

# Escape single quotes in the public key for the bash command
$escapedKey = $publicKey -replace "'", "'\\''"

# Run commands on the remote server
$installCmd = @"
mkdir -p ~/.ssh && \
chmod 700 ~/.ssh && \
echo '$escapedKey' >> ~/.ssh/authorized_keys && \
chmod 600 ~/.ssh/authorized_keys && \
echo 'SSH key installed successfully' || echo 'ERROR: Installation failed'
"@

ssh "$ServerUser@$ServerHost" $installCmd

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ SSH key installation complete!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Testing key authentication..." -ForegroundColor Cyan
    
    $privateKey = $KeyPath -replace '\.pub$', ''
    ssh -i $privateKey "$ServerUser@$ServerHost" "echo 'Key authentication works!'"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✅ SUCCESS! You can now use key-based authentication." -ForegroundColor Green
        Write-Host ""
        Write-Host "To deploy with this key, use:" -ForegroundColor Yellow
        Write-Host "  .\deploy.ps1 -ServerHost $ServerHost -KeyPath $privateKey" -ForegroundColor White
        Write-Host ""
    } else {
        Write-Host ""
        Write-Host "⚠️  Key was installed but authentication test failed." -ForegroundColor Yellow
        Write-Host "   This might be a permissions issue on the server." -ForegroundColor Yellow
        Write-Host "   Try logging in manually to debug." -ForegroundColor Yellow
    }
} else {
    Write-Host ""
    Write-Host "❌ Installation failed. Please check the error messages above." -ForegroundColor Red
}

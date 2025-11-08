#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Syncs custom styles from /custom-styles/ to phpbb/styles/
    
.DESCRIPTION
    Copies custom style files from the protected custom-styles directory
    to the phpbb styles directory. This ensures your custom styles are
    preserved in version control while keeping phpbb folder flexible.
    
.EXAMPLE
    .\sync-custom-styles.ps1
    Copies all custom styles to phpbb/styles/
    
.EXAMPLE
    .\sync-custom-styles.ps1 -StyleName "cu-fcchat"
    Copies only the specified style
#>

param(
    [Parameter(HelpMessage = "Specific style name to sync (default: all)")]
    [string]$StyleName = ""
)

$ErrorActionPreference = "Stop"

Write-Host "🎨 Syncing custom styles..." -ForegroundColor Cyan
Write-Host ""

$customStylesPath = Join-Path $PSScriptRoot "custom-styles"
$phpbbStylesPath = Join-Path $PSScriptRoot "phpbb\styles"

# Ensure directories exist
if (-not (Test-Path $customStylesPath)) {
    Write-Host "❌ Error: custom-styles directory not found" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $phpbbStylesPath)) {
    New-Item -Path $phpbbStylesPath -ItemType Directory -Force | Out-Null
    Write-Host "✓ Created phpbb/styles directory" -ForegroundColor Green
}

# Get styles to sync
if ($StyleName) {
    $stylesToSync = @(Get-ChildItem -Path $customStylesPath -Directory | Where-Object { $_.Name -eq $StyleName })
    if ($stylesToSync.Count -eq 0) {
        Write-Host "❌ Error: Style '$StyleName' not found in custom-styles/" -ForegroundColor Red
        exit 1
    }
} else {
    $stylesToSync = Get-ChildItem -Path $customStylesPath -Directory
}

if ($stylesToSync.Count -eq 0) {
    Write-Host "⚠️  No custom styles found in custom-styles/" -ForegroundColor Yellow
    exit 0
}

# Sync each style
foreach ($style in $stylesToSync) {
    $sourcePath = $style.FullName
    $destPath = Join-Path $phpbbStylesPath $style.Name
    
    Write-Host "📦 Syncing style: $($style.Name)" -ForegroundColor Cyan
    
    # Remove existing destination if it exists
    if (Test-Path $destPath) {
        Remove-Item -Path $destPath -Recurse -Force
        Write-Host "  ↳ Removed existing destination" -ForegroundColor Gray
    }
    
    # Copy style
    Copy-Item -Path $sourcePath -Destination $destPath -Recurse -Force
    Write-Host "  ✓ Copied to phpbb/styles/$($style.Name)" -ForegroundColor Green
    
    # Count files
    $fileCount = (Get-ChildItem -Path $destPath -Recurse -File).Count
    Write-Host "  ↳ $fileCount files synced" -ForegroundColor Gray
}

Write-Host ""
Write-Host "✅ Custom styles synced successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "💡 Tip: Run this script after:" -ForegroundColor Yellow
Write-Host "   - Restoring a backup" -ForegroundColor Gray
Write-Host "   - Pulling from live server" -ForegroundColor Gray
Write-Host "   - Fresh phpBB installation" -ForegroundColor Gray

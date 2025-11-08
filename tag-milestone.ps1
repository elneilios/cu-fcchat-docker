#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Creates a Git tag for a milestone release
    
.DESCRIPTION
    Creates an annotated Git tag with a standard format for tracking
    significant milestones in the repository. Optionally pushes to remote.
    
.PARAMETER Version
    Semantic version (e.g., "1.0.0", "1.1.0", "2.0.0")
    
.PARAMETER Message
    Description of this milestone (optional)
    
.PARAMETER Push
    Push the tag to remote repository after creating
    
.EXAMPLE
    .\tag-milestone.ps1 -Version "1.0.0" -Message "Initial production release"
    Creates tag v1.0.0 locally
    
.EXAMPLE
    .\tag-milestone.ps1 -Version "1.1.0" -Message "Added Exo 2 fonts" -Push
    Creates tag v1.1.0 and pushes to remote
    
.EXAMPLE
    .\tag-milestone.ps1 -Version "1.0.0" -Message "Style system implemented" -Push -Force
    Creates/overwrites tag v1.0.0 and force pushes to remote
#>

param(
    [Parameter(Mandatory=$true, HelpMessage = "Semantic version (e.g., 1.0.0)")]
    [ValidatePattern('^\d+\.\d+\.\d+$')]
    [string]$Version,
    
    [Parameter(HelpMessage = "Description of this milestone")]
    [string]$Message = "",
    
    [Parameter(HelpMessage = "Push tag to remote repository")]
    [switch]$Push,
    
    [Parameter(HelpMessage = "Force overwrite existing tag")]
    [switch]$Force
)

$ErrorActionPreference = "Stop"

Write-Host "🏷️  Creating milestone tag..." -ForegroundColor Cyan
Write-Host ""

$tagName = "v$Version"

# Check if tag already exists
$existingTag = git tag -l $tagName
if ($existingTag -and -not $Force) {
    Write-Host "❌ Error: Tag '$tagName' already exists" -ForegroundColor Red
    Write-Host "   Use -Force to overwrite" -ForegroundColor Yellow
    exit 1
}

# Get current branch and commit
$currentBranch = git rev-parse --abbrev-ref HEAD
$currentCommit = git rev-parse --short HEAD
$commitMessage = git log -1 --pretty=%B | Select-Object -First 1

Write-Host "📊 Current Status:" -ForegroundColor Cyan
Write-Host "   Branch:  $currentBranch" -ForegroundColor Gray
Write-Host "   Commit:  $currentCommit" -ForegroundColor Gray
Write-Host "   Message: $commitMessage" -ForegroundColor Gray
Write-Host ""

# Build tag message
if ([string]::IsNullOrWhiteSpace($Message)) {
    $tagMessage = "Release $Version"
} else {
    $tagMessage = "Release $Version`n`n$Message"
}

# Create tag
try {
    if ($Force -and $existingTag) {
        git tag -d $tagName | Out-Null
        Write-Host "  ↳ Removed existing tag" -ForegroundColor Gray
    }
    
    git tag -a $tagName -m $tagMessage
    Write-Host "✅ Created tag: $tagName" -ForegroundColor Green
    Write-Host ""
    
    # Show tag details
    Write-Host "📋 Tag Details:" -ForegroundColor Cyan
    git show $tagName --no-patch --format="%C(yellow)%h%Creset %C(white)%s%Creset%n%C(cyan)Tagger:%Creset %an <%ae>%n%C(cyan)Date:%Creset %ad%n%n%C(bold)Tag Message:%Creset%n%b"
    Write-Host ""
    
    # Push to remote if requested
    if ($Push) {
        Write-Host "🚀 Pushing tag to remote..." -ForegroundColor Cyan
        
        if ($Force) {
            git push origin $tagName --force
        } else {
            git push origin $tagName
        }
        
        Write-Host "✅ Tag pushed to remote" -ForegroundColor Green
        Write-Host ""
        Write-Host "🔗 View on GitHub: https://github.com/elneilios/cu-fcchat-docker/releases/tag/$tagName" -ForegroundColor Cyan
    } else {
        Write-Host "💡 Tip: Push tag with:" -ForegroundColor Yellow
        Write-Host "   git push origin $tagName" -ForegroundColor Gray
    }
    
} catch {
    Write-Host "❌ Error creating tag: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "📚 Common Tag Commands:" -ForegroundColor Yellow
Write-Host "   List tags:           git tag -l" -ForegroundColor Gray
Write-Host "   Show tag details:    git show $tagName" -ForegroundColor Gray
Write-Host "   Delete local tag:    git tag -d $tagName" -ForegroundColor Gray
Write-Host "   Delete remote tag:   git push origin --delete $tagName" -ForegroundColor Gray
Write-Host "   Checkout tag:        git checkout $tagName" -ForegroundColor Gray

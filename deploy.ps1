#Requires -Version 5.1
<#
.SYNOPSIS
    File Image Manager v1.0.0 - Windows Deploy Script

.DESCRIPTION
    Installs PHP and Node.js dependencies, builds the frontend,
    creates upload directories, and verifies the installation.

.PARAMETER Dev
    Install development dependencies.

.EXAMPLE
    .\deploy.ps1
    .\deploy.ps1 -Dev
#>

param(
    [switch]$Dev
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $ScriptDir

# ─── Helper functions ────────────────────────────────────────────────

function Write-Step($step, $msg) {
    Write-Host "`n[$step] $msg" -ForegroundColor Yellow
}

function Write-Ok($msg) {
    Write-Host "  OK: $msg" -ForegroundColor Green
}

function Write-Fail($msg) {
    Write-Host "  FAIL: $msg" -ForegroundColor Red
}

function Test-Command($name) {
    $null -ne (Get-Command $name -ErrorAction SilentlyContinue)
}

# ─── Banner ──────────────────────────────────────────────────────────

Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host " File Image Manager v1.0.0 Deploy" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan

# ─── Check requirements ──────────────────────────────────────────────

Write-Step "1/7" "Checking requirements..."

# PHP
if (-not (Test-Command "php")) {
    Write-Fail "PHP is not installed or not in PATH"
    Write-Host "  Download: https://windows.php.net/download/"
    exit 1
}
$phpVersion = php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;"
Write-Host "  PHP version: $phpVersion"

# Check PHP extensions
$requiredExts = @("gd", "mbstring", "json", "curl", "fileinfo")
$phpModules = php -m 2>$null
foreach ($ext in $requiredExts) {
    if ($phpModules -notcontains $ext) {
        Write-Fail "PHP extension '$ext' is not enabled"
        Write-Host "  Enable it in php.ini: extension=$ext"
        exit 1
    }
}
Write-Host "  PHP extensions: OK"

# Composer
if (-not (Test-Command "composer")) {
    Write-Fail "Composer is not installed"
    Write-Host "  Download: https://getcomposer.org/download/"
    exit 1
}
Write-Host "  Composer: $(composer --version 2>$null | Select-Object -First 1)"

# Node.js
if (-not (Test-Command "node")) {
    Write-Fail "Node.js is not installed"
    Write-Host "  Download: https://nodejs.org/"
    exit 1
}
Write-Host "  Node.js: $(node --version)"

# npm
if (-not (Test-Command "npm")) {
    Write-Fail "npm is not installed"
    exit 1
}
Write-Host "  npm: $(npm --version)"

# ─── Install PHP dependencies ────────────────────────────────────────

Write-Step "2/7" "Installing PHP dependencies..."

if ($Dev) {
    composer install --optimize-autoloader
} else {
    composer install --no-dev --optimize-autoloader --no-interaction
}
composer dump-autoload -o

# ─── Install Node dependencies ───────────────────────────────────────

Write-Step "3/7" "Installing Node.js dependencies..."

if ($Dev) {
    npm install --legacy-peer-deps
} else {
    try {
        npm ci --legacy-peer-deps --ignore-scripts 2>$null
    } catch {
        npm install --legacy-peer-deps
    }
}

# ─── Build frontend ──────────────────────────────────────────────────

Write-Step "4/7" "Building frontend..."

npm run build

# ─── Create directories ──────────────────────────────────────────────

Write-Step "5/7" "Creating upload directories..."

$sourceDir = Join-Path $ScriptDir "media\source"
$thumbsDir = Join-Path $ScriptDir "media\thumbs"

if (-not (Test-Path $sourceDir)) {
    New-Item -ItemType Directory -Path $sourceDir -Force | Out-Null
    Write-Host "  Created: $sourceDir"
} else {
    Write-Host "  Exists:  $sourceDir"
}

if (-not (Test-Path $thumbsDir)) {
    New-Item -ItemType Directory -Path $thumbsDir -Force | Out-Null
    Write-Host "  Created: $thumbsDir"
} else {
    Write-Host "  Exists:  $thumbsDir"
}

# ─── IIS permissions ─────────────────────────────────────────────────

Write-Step "6/7" "Setting IIS permissions..."

try {
    # Grant IIS_IUSRS write access to upload directories
    $acl = Get-Acl $sourceDir
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "IIS_IUSRS", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow"
    )
    $acl.SetAccessRule($rule)
    Set-Acl $sourceDir $acl

    $acl = Get-Acl $thumbsDir
    $acl.SetAccessRule($rule)
    Set-Acl $thumbsDir $acl

    Write-Host "  IIS_IUSRS: Modify permission granted to media/source/ and media/thumbs/"

    # Grant IIS_IUSRS read to public directory
    $publicDir = Join-Path $ScriptDir "public"
    $acl = Get-Acl $publicDir
    $readRule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "IIS_IUSRS", "ReadAndExecute", "ContainerInherit,ObjectInherit", "None", "Allow"
    )
    $acl.SetAccessRule($readRule)
    Set-Acl $publicDir $acl
    Write-Host "  IIS_IUSRS: ReadAndExecute permission granted to public/"
} catch {
    Write-Host "  Skipped: Could not set IIS permissions (run as Administrator)" -ForegroundColor DarkYellow
    Write-Host "  You may need to manually set permissions for IIS_IUSRS on:"
    Write-Host "    - $sourceDir (Modify)"
    Write-Host "    - $thumbsDir (Modify)"
}

# ─── Verify installation ─────────────────────────────────────────────

Write-Step "7/7" "Verifying installation..."

$errors = 0

# Check vendor autoload
if (Test-Path "vendor\autoload.php") {
    Write-Ok "vendor\autoload.php"
} else {
    Write-Fail "vendor\autoload.php not found"
    $errors++
}

# Check built assets
$assetsDir = Join-Path $ScriptDir "public\assets"
if (Test-Path $assetsDir) {
    $assetCount = (Get-ChildItem $assetsDir -Recurse -File).Count
    Write-Ok "public\assets\ ($assetCount files)"
} else {
    Write-Fail "public\assets\ not found (frontend not built?)"
    $errors++
}

# Check config
if (Test-Path "config\filemanager.php") {
    Write-Ok "config\filemanager.php"
} else {
    Write-Fail "config\filemanager.php not found"
    $errors++
}

# Check upload dirs
if (Test-Path $sourceDir) {
    Write-Ok "media\source\ exists"
} else {
    Write-Fail "media\source\ not found"
    $errors++
}

if (Test-Path $thumbsDir) {
    Write-Ok "media\thumbs\ exists"
} else {
    Write-Fail "media\thumbs\ not found"
    $errors++
}

# ─── Summary ─────────────────────────────────────────────────────────

Write-Host ""

if ($errors -gt 0) {
    Write-Host "Deploy completed with $errors error(s). Please fix the issues above." -ForegroundColor Red
    exit 1
}

Write-Host "=====================================" -ForegroundColor Green
Write-Host " Deploy completed successfully!" -ForegroundColor Green
Write-Host "=====================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:"
Write-Host "  1. In IIS Manager, create a site pointing to: $ScriptDir\public\"
Write-Host "  2. Ensure URL Rewrite module is installed"
Write-Host "  3. Edit config\filemanager.php if needed"
Write-Host ""

if ($Dev) {
    Write-Host "Development mode:"
    Write-Host "  npm run dev                            - Start Vite dev server (port 5173)"
    Write-Host "  php -S localhost:80 -t public\         - Start PHP built-in server"
}

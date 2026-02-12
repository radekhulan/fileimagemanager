#Requires -Version 5.1
<#
.SYNOPSIS
    File Image Manager v1.0.0 - Test Runner

.DESCRIPTION
    Runs PHP (PHPUnit) and frontend (Vitest) test suites.

.PARAMETER Suite
    Which suite to run: 'all' (default), 'php', 'frontend'.

.PARAMETER Watch
    Run frontend tests in watch mode.

.EXAMPLE
    .\test.ps1
    .\test.ps1 -Suite php
    .\test.ps1 -Suite frontend -Watch
#>

param(
    [ValidateSet("all", "php", "frontend")]
    [string]$Suite = "all",
    [switch]$Watch
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $ScriptDir

# Colors
function Write-Title($msg) {
    Write-Host "`n$msg" -ForegroundColor Cyan
}

function Write-Ok($msg) {
    Write-Host "  $msg" -ForegroundColor Green
}

function Write-Fail($msg) {
    Write-Host "  $msg" -ForegroundColor Red
}

$totalErrors = 0

# ─── PHP tests ────────────────────────────────────────────────────────

if ($Suite -eq "all" -or $Suite -eq "php") {
    Write-Title "Running PHP tests (PHPUnit)..."

    if (-not (Test-Path "vendor\bin\phpunit")) {
        Write-Fail "PHPUnit not found. Run: composer install"
        Write-Host "  (deploy.ps1 -Dev installs dev dependencies)" -ForegroundColor DarkYellow
        $totalErrors++
    } else {
        php vendor\bin\phpunit --colors=always
        if ($LASTEXITCODE -ne 0) {
            $totalErrors++
        }
    }
}

# ─── Frontend tests ──────────────────────────────────────────────────

if ($Suite -eq "all" -or $Suite -eq "frontend") {
    Write-Title "Running frontend tests (Vitest)..."

    if (-not (Test-Path "node_modules\.bin\vitest")) {
        Write-Fail "Vitest not found. Run: npm install"
        $totalErrors++
    } else {
        if ($Watch) {
            npx vitest --ui
        } else {
            npx vitest run
            if ($LASTEXITCODE -ne 0) {
                $totalErrors++
            }
        }
    }
}

# ─── Summary ──────────────────────────────────────────────────────────

Write-Host ""

if ($totalErrors -gt 0) {
    Write-Fail "Some test suites failed."
    exit 1
} else {
    Write-Ok "All test suites passed."
}

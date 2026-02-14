#!/usr/bin/env pwsh
# File Image Manager - Docker Build & Run
# Usage: .\docker\build.ps1 [-Run] [-Tag <name>] [-Port <port>]

param(
    [switch]$Run,
    [string]$Tag = "fileimagemanager",
    [int]$Port = 8080
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Write-Host "Building Docker image '$Tag' ..." -ForegroundColor Cyan
docker build -t $Tag $ProjectRoot

if ($LASTEXITCODE -ne 0) {
    Write-Host "Build failed." -ForegroundColor Red
    exit 1
}

Write-Host "Build OK: $Tag" -ForegroundColor Green

if ($Run) {
    Write-Host "Starting container on port $Port ..." -ForegroundColor Cyan
    $MediaPath = Join-Path $ProjectRoot "media"
    if (-not (Test-Path $MediaPath)) {
        New-Item -ItemType Directory -Path $MediaPath -Force | Out-Null
    }
    docker run --rm -p "${Port}:80" `
        -v "${MediaPath}:/var/www/html/media" `
        --name $Tag `
        $Tag

    # Note: --rm removes the container on stop. Media data persists in the named volume.
}

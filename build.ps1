$ErrorActionPreference = 'Stop'

Push-Location $PSScriptRoot

$default = (Get-Content package.json -Raw | ConvertFrom-Json).version
$input   = Read-Host "Version [$default]"
$version = if ($input) { $input } else { $default }
$name    = "fileimagemanager-v${version}"
$dist     = "dist"
$stage    = Join-Path $dist "fileimagemanager"
$zip      = Join-Path $dist "${name}.zip"

# Update package.json version if changed
if ($version -ne $default) {
    npm version $version --no-git-tag-version --allow-same-version 2>$null
    Write-Host "-> Updated package.json version to ${version}"
}

Write-Host "=== Building ${name} ===" -ForegroundColor Cyan

# 1. Frontend build
Write-Host "-> npm install"
npm ci --silent --legacy-peer-deps
if ($LASTEXITCODE -ne 0) { throw "npm ci failed" }

Write-Host "-> Frontend build"
npx vue-tsc --noEmit
if ($LASTEXITCODE -ne 0) { throw "vue-tsc failed" }

npx vite build
if ($LASTEXITCODE -ne 0) { throw "vite build failed" }

# 2. Production PHP dependencies
Write-Host "-> Composer install (production)"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
if ($LASTEXITCODE -ne 0) { throw "composer install failed" }

# 3. Clean previous build
if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
if (Test-Path $zip)   { Remove-Item $zip -Force }
New-Item -ItemType Directory -Path $stage -Force | Out-Null

# 4. Copy runtime files
Copy-Item -Recurse public/   (Join-Path $stage "public")
Copy-Item -Recurse src/      (Join-Path $stage "src")
Copy-Item -Recurse config/   (Join-Path $stage "config")
Copy-Item -Recurse lang/     (Join-Path $stage "lang")
Copy-Item -Recurse vendor/   (Join-Path $stage "vendor")
Copy-Item composer.json  $stage
Copy-Item composer.lock  $stage
Copy-Item web.config     $stage
Copy-Item README.md      $stage

# Remove local config override if copied
$localCfg = Join-Path $stage "config/filemanager.local.php"
if (Test-Path $localCfg) { Remove-Item $localCfg -Force }

# 5. Create zip
Write-Host "-> Creating ${zip}"
Compress-Archive -Path $stage -DestinationPath $zip -Force

# 6. Cleanup staging directory
Remove-Item $stage -Recurse -Force

# 7. Restore dev dependencies
Write-Host "-> Restoring dev dependencies"
composer install --no-interaction --quiet

$size = (Get-Item $zip).Length / 1MB
Write-Host ("=== Done: ${zip} ({0:N1} MB) ===" -f $size) -ForegroundColor Green

Pop-Location

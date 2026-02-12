#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

DEFAULT=$(node -p "require('./package.json').version")
read -rp "Version [${DEFAULT}]: " INPUT
VERSION="${INPUT:-$DEFAULT}"
NAME="fileimagemanager-v${VERSION}"
DIST="dist"
STAGE="${DIST}/fileimagemanager"
ZIP="${DIST}/${NAME}.zip"

# Update package.json version if changed
if [ "$VERSION" != "$DEFAULT" ]; then
    npm version "$VERSION" --no-git-tag-version --allow-same-version >/dev/null 2>&1
    echo "-> Updated package.json version to ${VERSION}"
fi

echo "=== Building ${NAME} ==="

# 1. Frontend build
echo "-> npm install"
npm ci --silent --legacy-peer-deps
echo "-> Frontend build"
npx vue-tsc --noEmit
npx vite build

# 2. Production PHP dependencies
echo "-> Composer install (production)"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

# 3. Clean previous build
rm -rf "${STAGE}" "${ZIP}"
mkdir -p "${STAGE}"

# 4. Copy runtime files
cp -r public/   "${STAGE}/public"
cp -r src/      "${STAGE}/src"
cp -r config/   "${STAGE}/config"
cp -r lang/     "${STAGE}/lang"
cp -r vendor/   "${STAGE}/vendor"
cp    composer.json  "${STAGE}/"
cp    composer.lock  "${STAGE}/"
cp    web.config     "${STAGE}/"
cp    README.md      "${STAGE}/"

# Remove local config override if copied
rm -f "${STAGE}/config/filemanager.local.php"

# 5. Create zip
echo "-> Creating ${ZIP}"
cd "${DIST}"
if command -v zip &>/dev/null; then
    zip -rq "${NAME}.zip" "fileimagemanager"
elif command -v 7z &>/dev/null; then
    7z a -tzip -bso0 "${NAME}.zip" "fileimagemanager"
else
    echo "ERROR: Neither zip nor 7z found. Install one to create the archive."
    exit 1
fi
cd ..

# 6. Cleanup staging directory
rm -rf "${DIST}/fileimagemanager"

# 7. Restore dev dependencies
echo "-> Restoring dev dependencies"
composer install --no-interaction --quiet

SIZE=$(du -h "${ZIP}" | cut -f1)
echo "=== Done: ${ZIP} (${SIZE}) ==="

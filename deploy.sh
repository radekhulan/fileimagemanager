#!/usr/bin/env bash
#
# File Image Manager v1.0.0 - Linux/macOS Deploy Script
#
# Usage:
#   chmod +x deploy.sh
#   ./deploy.sh [--dev]
#
# Options:
#   --dev    Install dev dependencies (for development)
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

DEV_MODE=false
if [[ "${1:-}" == "--dev" ]]; then
    DEV_MODE=true
fi

echo -e "${CYAN}=====================================${NC}"
echo -e "${CYAN} File Image Manager v1.0.0 Deploy${NC}"
echo -e "${CYAN}=====================================${NC}"
echo ""

# ─── Check requirements ──────────────────────────────────────────────

echo -e "${YELLOW}[1/7] Checking requirements...${NC}"

# PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}ERROR: PHP is not installed or not in PATH${NC}"
    exit 1
fi
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
echo "  PHP version: $PHP_VERSION"

# Check PHP extensions
REQUIRED_EXTS=("gd" "mbstring" "json" "curl" "fileinfo")
for ext in "${REQUIRED_EXTS[@]}"; do
    if ! php -m 2>/dev/null | grep -qi "^${ext}$"; then
        echo -e "${RED}ERROR: PHP extension '${ext}' is not installed${NC}"
        echo "  Install it with: sudo apt install php-${ext}  (Debian/Ubuntu)"
        echo "                   sudo dnf install php-${ext}  (Fedora/RHEL)"
        exit 1
    fi
done
echo "  PHP extensions: OK"

# Composer
if ! command -v composer &> /dev/null; then
    echo -e "${RED}ERROR: Composer is not installed${NC}"
    echo "  Install: https://getcomposer.org/download/"
    exit 1
fi
echo "  Composer: $(composer --version 2>/dev/null | head -1)"

# Node.js
if ! command -v node &> /dev/null; then
    echo -e "${RED}ERROR: Node.js is not installed${NC}"
    echo "  Install: https://nodejs.org/"
    exit 1
fi
echo "  Node.js: $(node --version)"

# npm
if ! command -v npm &> /dev/null; then
    echo -e "${RED}ERROR: npm is not installed${NC}"
    exit 1
fi
echo "  npm: $(npm --version)"

echo ""

# ─── Install PHP dependencies ────────────────────────────────────────

echo -e "${YELLOW}[2/7] Installing PHP dependencies...${NC}"
if [ "$DEV_MODE" = true ]; then
    composer install --optimize-autoloader
else
    composer install --no-dev --optimize-autoloader --no-interaction
fi
composer dump-autoload -o
echo ""

# ─── Install Node dependencies ───────────────────────────────────────

echo -e "${YELLOW}[3/7] Installing Node.js dependencies...${NC}"
if [ "$DEV_MODE" = true ]; then
    npm install --legacy-peer-deps
else
    npm ci --legacy-peer-deps --ignore-scripts 2>/dev/null || npm install --legacy-peer-deps
fi
echo ""

# ─── Build frontend ──────────────────────────────────────────────────

echo -e "${YELLOW}[4/7] Building frontend...${NC}"
npm run build
echo ""

# ─── Create directories ──────────────────────────────────────────────

echo -e "${YELLOW}[5/7] Creating upload directories...${NC}"

# Determine paths from config
SOURCE_DIR="${SCRIPT_DIR}/media/source"
THUMBS_DIR="${SCRIPT_DIR}/media/thumbs"

if [ ! -d "$SOURCE_DIR" ]; then
    mkdir -p "$SOURCE_DIR"
    echo "  Created: $SOURCE_DIR"
else
    echo "  Exists:  $SOURCE_DIR"
fi

if [ ! -d "$THUMBS_DIR" ]; then
    mkdir -p "$THUMBS_DIR"
    echo "  Created: $THUMBS_DIR"
else
    echo "  Exists:  $THUMBS_DIR"
fi

# Set permissions
chmod 755 "$SOURCE_DIR" "$THUMBS_DIR"
echo "  Permissions set to 755"
echo ""

# ─── Set file permissions ────────────────────────────────────────────

echo -e "${YELLOW}[6/7] Setting file permissions...${NC}"

# Config should be readable only by web server
chmod 640 config/filemanager.php 2>/dev/null || true

# Public directory
chmod -R 755 public/
echo "  Done"
echo ""

# ─── Verify installation ─────────────────────────────────────────────

echo -e "${YELLOW}[7/7] Verifying installation...${NC}"

ERRORS=0

# Check vendor autoload
if [ ! -f "vendor/autoload.php" ]; then
    echo -e "${RED}  FAIL: vendor/autoload.php not found${NC}"
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}  OK: vendor/autoload.php${NC}"
fi

# Check built assets
if [ ! -d "public/assets" ]; then
    echo -e "${RED}  FAIL: public/assets/ not found (frontend not built?)${NC}"
    ERRORS=$((ERRORS + 1))
else
    ASSET_COUNT=$(find public/assets -type f | wc -l)
    echo -e "${GREEN}  OK: public/assets/ ($ASSET_COUNT files)${NC}"
fi

# Check config
if [ ! -f "config/filemanager.php" ]; then
    echo -e "${RED}  FAIL: config/filemanager.php not found${NC}"
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}  OK: config/filemanager.php${NC}"
fi

# Check upload dirs
if [ -w "$SOURCE_DIR" ]; then
    echo -e "${GREEN}  OK: media/source/ writable${NC}"
else
    echo -e "${RED}  FAIL: media/source/ not writable${NC}"
    ERRORS=$((ERRORS + 1))
fi

if [ -w "$THUMBS_DIR" ]; then
    echo -e "${GREEN}  OK: media/thumbs/ writable${NC}"
else
    echo -e "${RED}  FAIL: media/thumbs/ not writable${NC}"
    ERRORS=$((ERRORS + 1))
fi

echo ""

# ─── Summary ─────────────────────────────────────────────────────────

if [ "$ERRORS" -gt 0 ]; then
    echo -e "${RED}Deploy completed with $ERRORS error(s). Please fix the issues above.${NC}"
    exit 1
fi

echo -e "${GREEN}=====================================${NC}"
echo -e "${GREEN} Deploy completed successfully!${NC}"
echo -e "${GREEN}=====================================${NC}"
echo ""
echo "Next steps:"
echo "  1. Point your web server document root to: ${SCRIPT_DIR}/public/"
echo "  2. Edit config/filemanager.php if needed"
echo ""

if [ "$DEV_MODE" = true ]; then
    echo "Development mode:"
    echo "  npm run dev        - Start Vite dev server (port 5173)"
    echo "  php -S localhost:80 -t public/  - Start PHP built-in server"
fi

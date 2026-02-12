#!/usr/bin/env bash
#
# File Image Manager v1.0.0 - Test Runner
#
# Usage:
#   chmod +x test.sh
#   ./test.sh              # run all tests
#   ./test.sh php          # run PHP tests only
#   ./test.sh frontend     # run frontend tests only
#   ./test.sh --watch      # run frontend tests in watch mode
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

SUITE="${1:-all}"
WATCH=false
ERRORS=0

for arg in "$@"; do
    case "$arg" in
        --watch) WATCH=true ;;
        php|frontend|all) SUITE="$arg" ;;
    esac
done

# ─── PHP tests ────────────────────────────────────────────────────────

if [[ "$SUITE" == "all" || "$SUITE" == "php" ]]; then
    echo -e "${CYAN}Running PHP tests (PHPUnit)...${NC}"

    if [ ! -f "vendor/bin/phpunit" ]; then
        echo -e "${RED}  PHPUnit not found. Run: composer install${NC}"
        echo "  (./deploy.sh --dev installs dev dependencies)"
        ERRORS=$((ERRORS + 1))
    else
        php vendor/bin/phpunit --colors=always || ERRORS=$((ERRORS + 1))
    fi

    echo ""
fi

# ─── Frontend tests ──────────────────────────────────────────────────

if [[ "$SUITE" == "all" || "$SUITE" == "frontend" ]]; then
    echo -e "${CYAN}Running frontend tests (Vitest)...${NC}"

    if [ ! -f "node_modules/.bin/vitest" ]; then
        echo -e "${RED}  Vitest not found. Run: npm install${NC}"
        ERRORS=$((ERRORS + 1))
    else
        if [ "$WATCH" = true ]; then
            npx vitest --ui
        else
            npx vitest run || ERRORS=$((ERRORS + 1))
        fi
    fi

    echo ""
fi

# ─── Summary ──────────────────────────────────────────────────────────

if [ "$ERRORS" -gt 0 ]; then
    echo -e "${RED}Some test suites failed.${NC}"
    exit 1
else
    echo -e "${GREEN}All test suites passed.${NC}"
fi

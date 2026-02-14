#!/usr/bin/env bash
# File Image Manager - Docker Build & Run
# Usage: ./docker/build.sh [--run] [--tag <name>] [--port <port>]

set -euo pipefail

TAG="fileimagemanager"
PORT=8080
RUN=false
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --run)  RUN=true; shift ;;
        --tag)  TAG="$2"; shift 2 ;;
        --port) PORT="$2"; shift 2 ;;
        *)      echo "Unknown option: $1"; exit 1 ;;
    esac
done

echo "Building Docker image '$TAG' ..."
docker build -t "$TAG" "$PROJECT_ROOT"

echo "Build OK: $TAG"

if [ "$RUN" = true ]; then
    echo "Starting container on port $PORT ..."
    MEDIA_PATH="${PROJECT_ROOT}/media"
    mkdir -p "$MEDIA_PATH"
    # Convert to Windows path for Docker Desktop bind mount
    if command -v cygpath &>/dev/null; then
        MEDIA_PATH="$(cygpath -w "$MEDIA_PATH")"
    fi
    docker run --rm -p "${PORT}:80" \
        -v "${MEDIA_PATH}:/var/www/html/media" \
        --name "$TAG" \
        "$TAG"
fi

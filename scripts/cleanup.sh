#!/usr/bin/env bash
set -euo pipefail

echo "== Akudihatinya Backend Cleanup =="

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RETENTION_DAYS=${RETENTION_DAYS:-30}

yellow() { echo -e "\033[33m$*\033[0m"; }
green() { echo -e "\033[32m$*\033[0m"; }

cd "$ROOT_DIR"

yellow "1) Clearing Laravel caches (config / route / view / events)"
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan event:clear || true

yellow "2) Pruning old log files (> ${RETENTION_DAYS}d)"
find storage/logs -type f -mtime +"$RETENTION_DAYS" -print -delete 2>/dev/null || true

yellow "3) Removing empty rotated logs"
find storage/logs -type f -size 0 -print -delete 2>/dev/null || true

yellow "4) Pruning old temporary export files (> ${RETENTION_DAYS}d)"
if [ -d public/exports ]; then
  find public/exports -type f -mtime +"$RETENTION_DAYS" -print -delete 2>/dev/null || true
fi

yellow "5) Clearing compiled classes cache (optimize:clear)"
php artisan optimize:clear || true

yellow "6) Optional: prune horizon / queue failed jobs (skipped if not installed)"
php artisan queue:prune-failed --hours=168 2>/dev/null || true

green "Cleanup complete."

echo "Tip: Run with RETENTION_DAYS=7 for aggressive pruning: RETENTION_DAYS=7 ./scripts/cleanup.sh"
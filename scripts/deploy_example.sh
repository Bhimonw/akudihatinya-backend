#!/usr/bin/env bash
set -euo pipefail

# Example manual deploy script (local -> server) using rsync
# Usage: ./scripts/deploy_example.sh user host /remote/path

USER="${1:-deploy}"
HOST="${2:-example.com}"
REMOTE_PATH="${3:-/var/www/akudihatinya}"

echo "[1/5] Building frontend"
( cd ../frontend-akudihatinya && npm ci && npm run build )

echo "[2/5] Syncing frontend build into backend"
powershell -ExecutionPolicy Bypass -File ./scripts/sync_frontend_build.ps1 || true

echo "[3/5] Installing backend prod dependencies"
composer install --no-dev --prefer-dist --no-interaction --no-progress

echo "[4/5] Creating archive"
tar -czf deploy-package.tar.gz . 

echo "[5/5] Uploading & extracting on remote"
scp deploy-package.tar.gz "$USER@$HOST:$REMOTE_PATH/deploy-package.tar.gz"
ssh "$USER@$HOST" "cd $REMOTE_PATH && tar -xzf deploy-package.tar.gz && rm deploy-package.tar.gz && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache"

echo "Done."
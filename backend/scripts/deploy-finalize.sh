#!/bin/bash
# Server-side deploy step, run after CI rsynced the built backend (incl. SPA in public/) to ~/$TEMP_DIR.
# Usage: deploy-finalize.sh <temp_dir> <app_dir>   (both relative to $HOME; web root = <app_dir>/public)

set -euo pipefail

TEMP_DIR="$1"
APP_DIR="$2"

echo "📦 Moving release into ~/$APP_DIR"
mkdir -p ~/"$APP_DIR"
# .env and storage/ (logs, cache, SQLite database) live only on the server.
rsync -a --delete \
  --exclude='.env' \
  --exclude='storage/' \
  ~/"$TEMP_DIR"/ ~/"$APP_DIR"/

cd ~/"$APP_DIR"

if [ ! -f .env ]; then
  echo "❌ ~/$APP_DIR/.env is missing. Create it from .env.example (APP_KEY, FLOW_API_URL, SHAREPOINT_*)."
  exit 1
fi

echo "📁 Preparing storage"
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
touch storage/app/hero.sqlite
find storage -type d -exec chmod 775 {} \; 2>/dev/null || true

echo "🔄 Migrating"
php artisan migrate --force

echo "🧹 Caching config and routes"
php artisan config:cache
php artisan route:cache
php artisan view:clear

echo "✅ Deploy finalized"

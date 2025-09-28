#!/usr/bin/env bash

set -euo pipefail

# Deployment script for Seepferdchen-Garde (Symfony + Webpack Encore):
# - Installs PHP and Node deps if missing
# - Builds front-end assets (production)
# - Clears & warms Symfony cache (prod)

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

# Ensure Composer/Symfony auto-scripts run in production context
export APP_ENV=prod
export APP_DEBUG=0
export SYMFONY_ENV=prod

# 1st: check if composer is available and then install composer dependencies
if command -v composer >/dev/null 2>&1; then
  echo "[1/4] Ensuring Composer dependencies are installed (no-dev, prod env)..."
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
  echo "Composer not found. Skipping Composer install." >&2
fi

# 2nd: check if npm is available and then install node dependencies
if command -v npm >/dev/null 2>&1; then
  echo "[2/4] Installing Node.js dependencies..."
  npm ci || npm install
else
  echo "npm not found. Skipping npm install." >&2
fi

# 3rd: check if npm is available and then build assets
if command -v npm >/dev/null 2>&1; then
  echo "[3/4] Building production assets with Encore..."
  npm run build
else
  echo "npm not found. Cannot build assets." >&2
fi

# 4th: check if php is available and then clear & warmup symfony cache
if command -v php >/dev/null 2>&1; then
  echo "[4/4] Clearing & warming Symfony cache (prod)..."
  php bin/console cache:clear --env=prod --no-warmup
  php bin/console cache:warmup --env=prod
else
  echo "PHP not found. Skipping Symfony cache steps." >&2
fi

echo "Deployment steps completed successfully."

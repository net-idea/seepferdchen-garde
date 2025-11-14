#!/bin/bash

set -euo pipefail

# Deployment script for Symfony + Webpack Encore:
# - Installs PHP and Node deps if missing
# - Builds front-end assets (production)
# - Runs database migrations (if SKIP_MIGRATIONS != true)
# - Clears & warms Symfony cache (prod)
#
# Usage:
#   ./deploy.sh                        # Normal deployment with migrations
#   SKIP_MIGRATIONS=true ./deploy.sh   # Skip database migrations

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

# Ensure Composer/Symfony auto-scripts run in production context
export APP_ENV=prod
export APP_DEBUG=0
export SYMFONY_ENV=prod

echo "Starting deployment for Symfony + Webpack Encore..."
echo "Environment: APP_ENV=$APP_ENV"
echo ""

# 1st: check if composer is available and then install composer dependencies
if command -v composer >/dev/null 2>&1; then
  echo "[1/5] Installing Composer dependencies (no-dev, prod env)..."
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  echo "✓ Composer dependencies installed"
else
  echo "⚠ Composer not found. Skipping Composer install." >&2
fi

echo ""

# 2nd: check if yarn/npm is available and then install node dependencies
if command -v yarn >/dev/null 2>&1; then
  echo "[2/5] Installing Node.js dependencies (yarn)..."
  yarn install --immutable 2>/dev/null || yarn install --frozen-lockfile 2>/dev/null || yarn install
  echo "✓ Node.js dependencies installed"
elif command -v npm >/dev/null 2>&1; then
  echo "[2/5] Installing Node.js dependencies (npm)..."
  npm ci 2>/dev/null || npm install
  echo "✓ Node.js dependencies installed"
else
  echo "⚠ Neither yarn nor npm found. Skipping Node.js install." >&2
fi

echo ""

# 3rd: check if yarn/npm is available and then build assets
if command -v yarn >/dev/null 2>&1; then
  echo "[3/5] Building production assets with Webpack Encore (yarn)..."
  yarn run build
  echo "✓ Production assets built"
elif command -v npm >/dev/null 2>&1; then
  echo "[3/5] Building production assets with Webpack Encore (npm)..."
  npm run build
  echo "✓ Production assets built"
else
  echo "✗ Neither yarn nor npm found. Cannot build assets." >&2
  exit 1
fi

echo ""

# 4th: check if php is available and then run database migrations
if [ "${SKIP_MIGRATIONS:-false}" = "true" ]; then
  echo "[4/5] Skipping database migrations (SKIP_MIGRATIONS=true)"
elif command -v php >/dev/null 2>&1; then
  # Only attempt to run migrations if there are actual migration classes in the configured folder.
  # This avoids the Doctrine error when no migrations are registered (e.g. clean installs without migrations).
  MIGRATIONS_DIR="$ROOT_DIR/migrations"
  MIGRATION_FILES="$(find "$MIGRATIONS_DIR" -maxdepth 1 -type f -name 'Version*.php' -print -quit 2>/dev/null || true)"

  if [ -n "$MIGRATION_FILES" ]; then
    echo "[4/5] Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --env=prod
    echo "✓ Database migrations completed"
  else
    echo "[4/5] No migration classes found in $MIGRATIONS_DIR — skipping migrations"
  fi
else
  echo "⚠ PHP not found. Skipping database migrations." >&2
fi

echo ""

# 5th: check if php is available and then clear & warmup symfony cache
if command -v php >/dev/null 2>&1; then
  echo "[5/5] Clearing & warming Symfony cache (prod)..."
  php bin/console cache:clear --env=prod --no-debug --no-warmup
  php bin/console cache:warmup --env=prod
  echo "✓ Symfony cache cleared and warmed"
else
  echo "✗ PHP not found. Cannot manage Symfony cache." >&2
  exit 1
fi

echo ""
echo "═══════════════════════════════════════════════════════"
echo "✓ Deployment completed successfully!"
echo "═══════════════════════════════════════════════════════"

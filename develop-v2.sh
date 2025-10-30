#!/bin/bash

set -euo pipefail

# Development environment setup script for Seepferdchen-Garde
# Features:
# - Installs Composer and Yarn dependencies if missing
# - Initializes database (SQLite/Docker) if needed
# - Runs frontend watcher and Symfony server simultaneously
# - Supports multiple terminal methods (AppleScript, tmux, background)
#
# Usage:
#   ./develop.sh              # Normal start with dependency check
#   SKIP_DEPS=true ./develop.sh   # Skip dependency installation

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$SCRIPT_DIR"

# Determine which server command to use
if command -v symfony >/dev/null 2>&1; then
  SERVER_CMD="symfony server:start"
  SERVER_NAME="Symfony CLI"
else
  SERVER_CMD="php -S localhost:8000 -t public"
  SERVER_NAME="PHP built-in server"
fi

WATCH_CMD="yarn run watch"

check_environment() {
  echo "════════════════════════════════════════════════════════"
  echo "  Seepferdchen-Garde Development Environment Setup"
  echo "════════════════════════════════════════════════════════"
  echo ""
  echo "→ Checking environment..."

  # Check for .env file
  if [[ ! -f "$PROJECT_DIR/.env" ]]; then
    echo "⚠ Warning: .env file not found. Copying from .env.dist..."
    cp "$PROJECT_DIR/.env.dist" "$PROJECT_DIR/.env"
    echo "✓ Created .env file. Please review and update it if needed."
  fi

  # Check APP_ENV
  local app_env="${APP_ENV:-dev}"
  echo "  Environment: $app_env"
  echo "  Server: $SERVER_NAME"
  echo ""
}

ensure_dependencies() {
  if [ "${SKIP_DEPS:-false}" = "true" ]; then
    echo "→ Skipping dependency installation (SKIP_DEPS=true)"
    return
  fi

  cd "$PROJECT_DIR"

  # Composer dependencies (include dev to avoid DebugBundle errors in dev)
  if command -v composer >/dev/null 2>&1; then
    echo "→ Installing PHP dependencies (composer install)"
    composer install --no-interaction --prefer-dist --quiet
    echo "✓ Composer dependencies installed"
  elif [[ -f composer.phar ]]; then
    echo "→ Installing PHP dependencies (composer.phar install)"
    php composer.phar install --no-interaction --prefer-dist --quiet
    echo "✓ Composer dependencies installed"
  else
    echo "✗ composer not found. Please install Composer: https://getcomposer.org/download/" >&2
    exit 1
  fi

  # Yarn/npm dependencies
  if command -v yarn >/dev/null 2>&1; then
    YARN_VER="$(yarn -v 2>/dev/null || yarn --version 2>/dev/null || echo 1)"
    echo "→ Installing Node dependencies (yarn ${YARN_VER})"

    # Try different yarn install methods with fallback
    if [[ "$YARN_VER" == 1.* ]]; then
      if yarn install --frozen-lockfile 2>/dev/null; then
        echo "✓ Node dependencies installed (yarn)"
      elif yarn install 2>/dev/null; then
        echo "✓ Node dependencies installed (yarn)"
      else
        echo "⚠ Yarn install failed, trying npm..."
        if command -v npm >/dev/null 2>&1; then
          npm install
          echo "✓ Node dependencies installed (npm)"
        else
          echo "✗ Both yarn and npm failed to install dependencies" >&2
          exit 1
        fi
      fi
    else
      if yarn install --immutable 2>/dev/null; then
        echo "✓ Node dependencies installed (yarn)"
      elif yarn install 2>/dev/null; then
        echo "✓ Node dependencies installed (yarn)"
      else
        echo "⚠ Yarn install failed, trying npm..."
        if command -v npm >/dev/null 2>&1; then
          npm install
          echo "✓ Node dependencies installed (npm)"
        else
          echo "✗ Both yarn and npm failed to install dependencies" >&2
          exit 1
        fi
      fi
    fi
  elif command -v npm >/dev/null 2>&1; then
    echo "→ Installing Node dependencies (npm)"
    npm ci 2>/dev/null || npm install
    echo "✓ Node dependencies installed (npm)"
  else
    echo "✗ Neither yarn nor npm found. Install via: brew install yarn (macOS) or see https://yarnpkg.com/getting-started/install" >&2
    exit 1
  fi

  echo ""
}

setup_database() {
  cd "$PROJECT_DIR"

  echo "→ Checking database setup..."

  # Check if we're using Docker
  if [[ -f "$PROJECT_DIR/compose.yaml" ]] && command -v docker >/dev/null 2>&1; then
    # Check if Docker services are running
    if docker compose ps 2>/dev/null | grep -q "database"; then
      echo "✓ Docker database service is running"
    else
      # Check if DATABASE_URL in .env points to Docker
      if grep -q "postgresql://.*127.0.0.1.*5432" "$PROJECT_DIR/.env" 2>/dev/null || \
         grep -q "mysql://.*127.0.0.1.*3306" "$PROJECT_DIR/.env" 2>/dev/null; then
        echo "→ Starting Docker database services..."
        docker compose up -d database
        echo "✓ Docker database started"
        echo "  Waiting for database to be ready (5 seconds)..."
        sleep 5
      fi
    fi
  fi

  # Check if database needs initialization
  if command -v php >/dev/null 2>&1; then
    # Try to check database connection
    if php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; then
      echo "✓ Database connection successful"
    else
      echo "→ Database not initialized. Setting up..."

      # Create database if it doesn't exist (works for SQLite too)
      php bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true

      # Run migrations if they exist
      if [[ -n "$(ls -A migrations/ 2>/dev/null)" ]]; then
        echo "→ Running database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction
        echo "✓ Database migrations completed"
      else
        # No migrations, try schema update
        echo "→ Creating database schema..."
        php bin/console doctrine:schema:update --force --no-interaction
        echo "✓ Database schema created"
      fi
    fi
  fi

  echo ""
}

run_with_applescript() {
  echo ""
  echo "════════════════════════════════════════════════════════"
  echo "✓ Opening development servers in new Terminal windows"
  echo "════════════════════════════════════════════════════════"
  echo ""
  echo "  Window 1: Asset Watcher ($WATCH_CMD)"
  echo "  Window 2: Server ($SERVER_CMD)"
  echo ""
  echo "  Server URL: http://localhost:8000"
  echo ""

  /usr/bin/osascript <<OSA
  tell application "Terminal"
    do script "cd '$PROJECT_DIR'; echo 'Starting Asset Watcher...'; echo ''; $WATCH_CMD"
    do script "cd '$PROJECT_DIR'; echo 'Starting $SERVER_NAME...'; echo ''; $SERVER_CMD"
    activate
  end tell
OSA
}

run_with_tmux() {
  echo ""
  echo "════════════════════════════════════════════════════════"
  echo "✓ Starting development servers in tmux session"
  echo "════════════════════════════════════════════════════════"
  echo ""
  echo "  Top pane: Asset Watcher"
  echo "  Bottom pane: $SERVER_NAME"
  echo ""
  echo "  Server URL: http://localhost:8000"
  echo ""
  echo "  tmux commands:"
  echo "    Ctrl+B, then D - Detach session"
  echo "    tmux attach -t dev-seepferdchen - Re-attach"
  echo ""

  local session="dev-seepferdchen"
  tmux has-session -t "$session" 2>/dev/null && tmux kill-session -t "$session" 2>/dev/null || true
  tmux new-session -d -s "$session" "cd '$PROJECT_DIR'; echo 'Starting Asset Watcher...'; echo ''; $WATCH_CMD"
  tmux split-window -v -t "$session" "cd '$PROJECT_DIR'; echo 'Starting $SERVER_NAME...'; echo ''; $SERVER_CMD"
  tmux select-layout -t "$session" even-vertical
  tmux attach -t "$session"
}

run_in_background() {
  cd "$PROJECT_DIR"

  echo ""
  echo "Starting: $WATCH_CMD"
  bash -lc "$WATCH_CMD" &
  WATCH_PID=$!

  sleep 2

  echo "Starting: $SERVER_CMD"
  bash -lc "$SERVER_CMD" &
  SERVER_PID=$!

  echo ""
  echo "════════════════════════════════════════════════════════"
  echo "✓ Development environment is running!"
  echo "════════════════════════════════════════════════════════"
  echo ""
  echo "  Asset Watcher PID: $WATCH_PID"
  echo "  Server PID: $SERVER_PID"
  echo ""
  echo "  Server URL: http://localhost:8000"
  echo ""
  echo "  Press Ctrl+C to stop both processes"
  echo ""

  trap 'echo ""; echo "Stopping development servers..."; kill "$WATCH_PID" "$SERVER_PID" 2>/dev/null || true; echo "✓ Stopped"; exit 0' INT TERM EXIT

  # Wait on both; if one exits, keep waiting for the other until both are done
  wait "$WATCH_PID" || true
  wait "$SERVER_PID" || true
}

main() {
  check_environment
  ensure_dependencies
  setup_database

  echo "════════════════════════════════════════════════════════"
  echo "  Starting Development Servers"
  echo "════════════════════════════════════════════════════════"
  echo ""
  echo "→ Starting asset watcher and $SERVER_NAME..."
  echo ""

  if [[ "$(uname -s)" == "Darwin" ]] && command -v osascript >/dev/null 2>&1; then
    echo "Using macOS Terminal (separate windows)"
    run_with_applescript
  elif command -v tmux >/dev/null 2>&1; then
    echo "Using tmux (split panes)"
    run_with_tmux
  else
    echo "Using background processes"
    run_in_background
  fi
}

main "$@"

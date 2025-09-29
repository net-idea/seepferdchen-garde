#!/bin/bash

set -euo pipefail

# Install Composer and Yarn dependencies if missing.
# Run frontend watcher and Symfony server simultaneously.
# Preference order:
# 1) macOS Terminal via AppleScript: open two windows and run each command.
# 2) tmux (if installed): two panes in a session.
# 3) Fallback: run both in background in the current terminal with cleanup.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$SCRIPT_DIR"

WATCH_CMD="yarn run watch"
SERVER_CMD="symfony server:start"

ensure_dependencies() {
  cd "$PROJECT_DIR"

  # Composer dependencies (include dev to avoid DebugBundle errors in dev)
  if command -v composer >/dev/null 2>&1; then
    echo "→ Installing PHP dependencies (composer install)"
    composer install --no-interaction --prefer-dist --no-progress
  elif [[ -f composer.phar ]]; then
    echo "→ Installing PHP dependencies (composer.phar install)"
    php composer.phar install --no-interaction --prefer-dist --no-progress
  else
    echo "✖ composer not found. Please install Composer: https://getcomposer.org/download/" >&2
    exit 1
  fi

  # Yarn dependencies (use --immutable for Yarn ≥2, else --frozen-lockfile)
  if command -v yarn >/dev/null 2>&1; then
    YARN_VER="$(yarn -v 2>/dev/null || yarn --version 2>/dev/null || echo 1)"
    echo "→ Installing Node dependencies (yarn ${YARN_VER})"
    if [[ "$YARN_VER" == 1.* ]]; then
      yarn install --frozen-lockfile
    else
      yarn install --immutable
    fi
  else
    echo "✖ yarn not found. Install via: brew install yarn (macOS) or see https://yarnpkg.com/getting-started/install" >&2
    exit 1
  fi
}

run_with_applescript() {
  /usr/bin/osascript <<OSA
  tell application "Terminal"
    do script "cd '$PROJECT_DIR'; $WATCH_CMD"
    do script "cd '$PROJECT_DIR'; $SERVER_CMD"
    activate
  end tell
OSA
}

run_with_tmux() {
  local session="dev-seepferdchen"
  tmux has-session -t "$session" 2>/dev/null && tmux kill-session -t "$session" 2>/dev/null || true
  tmux new-session -d -s "$session" "cd '$PROJECT_DIR'; $WATCH_CMD"
  tmux split-window -v -t "$session" "cd '$PROJECT_DIR'; $SERVER_CMD"
  tmux select-layout -t "$session" even-vertical
  tmux attach -t "$session"
}

run_in_background() {
  cd "$PROJECT_DIR"
  echo "Starting: $WATCH_CMD"
  bash -lc "$WATCH_CMD" &
  WATCH_PID=$!

  echo "Starting: $SERVER_CMD"
  bash -lc "$SERVER_CMD" &
  SERVER_PID=$!

  echo "Started yarn watch (PID $WATCH_PID) and symfony server (PID $SERVER_PID). Press Ctrl+C to stop."
  trap 'kill "$WATCH_PID" "$SERVER_PID" 2>/dev/null || true' INT TERM EXIT

  # Wait on both; if one exits, keep waiting for the other until both are done
  wait "$WATCH_PID" || true
  wait "$SERVER_PID" || true
}

main() {
  ensure_dependencies

  if [[ "$(uname -s)" == "Darwin" ]] && command -v osascript >/dev/null 2>&1; then
    run_with_applescript
  elif command -v tmux >/dev/null 2>&1; then
    run_with_tmux
  else
    run_in_background
  fi
}

main "$@"

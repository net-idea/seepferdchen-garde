#!/bin/bash

### Development Environment Script
# Script to create a development environment for your project.
# Following tasks are performed:
# 1. Install Yarn dependencies
# 2. Install Composer dependencies
# 4. Clear Symfony cache
# 5. Build front-end assets (once)
# 6. Check if database migrations are available
# 6.1. If yes, prompt user with question to run them
# 7. Run frontend watcher and Symfony server simultaneously:
# 7.1. Start Webpack Encore Dev Server (yarn encore dev --watch)
# 7.2. Start Symfony Development Server (symfony server:start --no-tls --port

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Project directory (current working directory)
PROJECT_DIR=$(pwd)
PHP_BIN=$(which php 2>/dev/null || echo "")
COMPOSER_BIN=$(which composer 2>/dev/null || echo "")
SYMFONY_BIN=$(which symfony 2>/dev/null || echo "")
NODE_BIN=$(which node 2>/dev/null || echo "")
YARN_BIN=$(which yarn 2>/dev/null || echo "")

# Trap Ctrl+C to gracefully stop background processes
trap 'echo -e "${YELLOW}Stopping development environment...${NC}"; kill $YARN_PID $PHP_PID 2>/dev/null; exit 0' INT TERM

echo -e "${GREEN}Starting Symfony + Webpack Encore development environment...${NC}"

# === 1. Check dependencies ===
echo -e "${YELLOW}Checking dependencies...${NC}"

if [ -z "$PHP_BIN" ]; then
    echo -e "${RED}PHP is not installed or not in PATH.${NC}" >&2
    exit 1
fi

if [ -z "$SYMFONY_BIN" ]; then
    echo -e "${RED}Symfony CLI is not installed. Install with: curl -sS https://get.symfony.com/cli/installer | bash${NC}" >&2
    exit 1
fi

if [ -z "$COMPOSER_BIN" ]; then
    echo -e "${RED}Composer is not installed.${NC}" >&2
    exit 1
fi

if [ -z "$NODE_BIN" ]; then
    echo -e "${RED}Node.js is not installed.${NC}" >&2
    exit 1
fi

if [ -z "$YARN_BIN" ]; then
    echo -e "${RED}Yarn is not installed. Install with: npm install -g yarn${NC}" >&2
    exit 1
fi

echo -e "${GREEN}All dependencies are available.${NC}"

# === 2. Run Composer install ===
echo -e "${YELLOW}Running Composer install...${NC}"
$COMPOSER_BIN install --working-dir="$PROJECT_DIR" --prefer-dist --no-progress --no-suggest
if [ $? -ne 0 ]; then
    echo -e "${RED}Composer install failed.${NC}" >&2
    exit 1
fi

# === 3. Run Yarn install ===
echo -e "${YELLOW}Running Yarn install...${NC}"
$YARN_BIN install --cwd "$PROJECT_DIR"
if [ $? -ne 0 ]; then
    echo -e "${RED}Yarn install failed.${NC}" >&2
    exit 1
fi

# === 4. Clear and warm up Symfony cache ===
echo -e "${YELLOW}Clearing and warming up cache...${NC}"
$PHP_BIN bin/console cache:clear --no-warmup
$PHP_BIN bin/console cache:warmup

# === 5. Doctrine Migrations (only if migrations exist) ===
if [ -d "migrations" ] && [ "$(ls -A migrations/*.php 2>/dev/null | wc -l)" -gt 0 ]; then
    echo
    echo -e "${YELLOW}Migrations found. Run them now? (y/N)${NC}"
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Running migrations...${NC}"
        $PHP_BIN bin/console doctrine:migrations:migrate --no-interaction
    else
        echo -e "${GREEN}Migrations skipped.${NC}"
    fi
else
    echo -e "${GREEN}No migrations found.${NC}"
fi

# === 6. Start Yarn watch and PHP built-in server ===
echo
echo -e "${GREEN}Starting development servers...${NC}"
echo -e "${YELLOW}   → Yarn Encore: http://localhost:8080 (assets)${NC}"
echo -e "${YELLOW}   → PHP Server:  http://127.0.0.1:8000${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop.${NC}"
echo

# Start Yarn Encore in watch mode (background)
$YARN_BIN encore dev --watch &
YARN_PID=$!

# Give it a moment to start
sleep 2

# Start PHP built-in server
$PHP_BIN -S 127.0.0.1:8000 -t public &
PHP_PID=$!

# Wait for processes (will be interrupted by trap on Ctrl+C)
wait

# Seepferdchen‑Garde

**The official website of the Seepferdchen‑Garde, a swimming school founded by Riccardo Nappa.**

Website: https://seepferdchen-garde.de/

---

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Configuration (.env)](#configuration-env)
- [Running the app (dev)](#running-the-app-dev)
- [Database (SQLite, Postgres, MariaDB)](#database-sqlite-postgres-mariadb)
- [Assets & HMR](#assets--hmr)
- [Emails](#emails)
- [PDF generation](#pdf-generation)
- [Linting & tests](#linting--tests)
- [Production build & deployment](#production-build--deployment)
- [Docker notes](#docker-notes)
- [Troubleshooting](#troubleshooting)
- [License & authors](#license--authors)

## Overview

- Backend: Symfony 7.3 (PHP 8.2+), Doctrine ORM, Twig.
- Frontend: Webpack Encore, TypeScript, SCSS, Stimulus.
- Database: SQLite by default; Postgres or MariaDB supported.
- Mail: Symfony Mailer (SMTP). Mailpit config for local testing included.
- Tooling: Composer, Yarn, Stylelint, PHP CS Fixer, PHPUnit.

## Requirements

- PHP >= 8.2 with extensions: ctype, dom, iconv, json, xml, xmlwriter
- Composer
- Node.js 18+ (LTS recommended) and Yarn (v1 or v2+)
- SQLite (built-in) or Docker if you prefer Postgres/MariaDB
- Optional: Symfony CLI for local server (https://symfony.com/download)

## Quick start

Clone and install dependencies:

```shell
git clone git@github.com:net-idea/seepferdchen-garde.git seepferdchen-garde
cd seepferdchen-garde

# PHP dependencies (dev included)
composer install

# Node dependencies
yarn install
```

Create your environment file and app secret:

```shell
cp .env.dist .env
php bin/console regenerate-app-secret
```

Copy the generated value into `.env` at `APP_SECRET=...` (or export it as a real environment variable).

Initialize the database schema (SQLite by default):

```shell
# Recommended: run migrations if present
php bin/console doctrine:migrations:migrate -n

# Fallback (if you prefer schema update)
php bin/console doctrine:schema:update --force
```

Run the app locally:

```shell
# Option A: Symfony local server (recommended)
symfony server:start

# Option B: PHP built-in server (serve the public/ dir)
php -S localhost:8000 -t public
```

In a second terminal, start the asset watcher or HMR (see "Assets & HMR").

## Configuration (.env)

All configuration is via environment variables. The committed `.env.dist` documents sane defaults. Typical keys you may change:

- APP_ENV: dev | prod (default: dev)
- APP_SECRET: random string; generate via `php bin/console regenerate-app-secret`
- DEFAULT_URI: base URL used for URL generation in CLI contexts (e.g. http://localhost)
- DATABASE_URL: Doctrine DSN. Defaults to SQLite per environment.
    - SQLite (default):
        - `DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_%kernel.environment%.db"`
    - MariaDB/MySQL example:
        - `DATABASE_URL="mysql://user:pass@127.0.0.1:3306/db?serverVersion=10.11.2-MariaDB&charset=utf8mb4"`
    - Postgres example:
        - `DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/db?serverVersion=16&charset=utf8"`
- MESSENGER_TRANSPORT_DSN: default `doctrine://default?auto_setup=0`.
    - For simple dev setups, you can use `sync://`.
- Mail settings (compose into MAILER_DSN):
    - MAIL_SCHEME, MAIL_HOST, MAIL_ENCRYPTION, MAIL_PORT, MAIL_USER, MAIL_PASSWORD
    - MAILER_DSN example for local Mailpit: `smtp://localhost:1025?encryption=&auth_mode=`
    - Sender/recipient defaults for app mail flows:
        - MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_TO_ADDRESS, MAIL_TO_NAME

Security note: Do not commit production secrets. Prefer real environment variables or Symfony Secrets Vault for prod.

## Running the app (dev)

Two common workflows:

- Minimal watcher + server
  ```shell
  # Terminal 1: asset watcher
  yarn watch

  # Terminal 2: PHP server
  symfony server:start
  # …or…
  php -S localhost:8000 -t public
  ```

- Hot Module Replacement (HMR)
  ```shell
  # Terminal 1: Webpack dev server with HMR (port 8080)
  yarn dev-server

  # Terminal 2: Symfony server to serve the backend
  symfony server:start
  ```

Optional helper (macOS/tmux):

```shell
./develop.sh
```

This script installs dependencies, then starts the watcher and the Symfony server in separate terminals/panes.

## Database (SQLite, Postgres, MariaDB)

Default is SQLite per environment with files under `var/data_*.db`.

Schema management:

```shell
# Preferred when migrations exist
php bin/console doctrine:migrations:migrate -n

# Alternative
php bin/console doctrine:schema:update --force
```

Using Postgres via Docker (from `compose.yaml`):

```shell
docker compose up -d database
# Then point DATABASE_URL to your Postgres instance, e.g.
# postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8
```

Using MariaDB via Docker (see `docker-compose.mariadb.yml` + `docker-compose.mariadb.dev.yml`):

```shell
docker compose -f docker-compose.mariadb.yml -f docker-compose.mariadb.dev.yml up -d
# Host: 127.0.0.1, Port: 3316 (mapped)
# Example DATABASE_URL (MariaDB 10.11):
# mysql://seepferdchen-garde:nopassword@127.0.0.1:3316/seepferdchen-garde?serverVersion=10.11.2-MariaDB&charset=utf8mb4
```

After switching databases, (re)create schema/migrate accordingly.

## Assets & HMR

- One-time build (dev):
  ```shell
  yarn dev
  ```
- Watch for changes:
  ```shell
  yarn watch
  ```
- Hot Module Replacement (port 8080):
  ```shell
  yarn dev-server
  ```
- Production build:
  ```shell
  yarn build
  ```

OG image generation:

```shell
# Already available via script
yarn og:build
# or
node generate-og.js
```

The images will be written to `public/assets/og/` and match the paths used in `content/_pages.php`.

## Emails

Local testing (Mailpit):

```shell
# Run mailer dev container (from compose.override.yaml)
docker compose up -d mailer
# Web UI: http://localhost:8025 (SMTP on 1025)
# Set MAILER_DSN=smtp://localhost:1025?encryption=&auth_mode=
```

Useful commands:

```shell
php bin/console app:mail:test -vvv
php bin/console app:mail:preview-contact -vvv
php bin/console app:mail:preview-booking -vvv
php bin/console app:list:bookings -vvv
php bin/console app:list:contacts -vvv

# If Messenger transport fails (prod example):
php bin/console messenger:failed:show --env=prod
```

## PDF generation

Create the registration form PDF:

```shell
./pdf.sh public/docs/2025.11-voanmeldung-schwimmkurs
```

## Linting & tests

- Run all local linters and formatters:
  ```shell
  ./lint.sh
  ```
- PHP CS Fixer only:
  ```shell
  ./php-cs-fixer.sh
  ```
- PHPUnit test suite:
  ```shell
  ./phpunit.sh
  # or
  vendor/bin/phpunit
  ```
- TypeScript type check:
  ```shell
  yarn tsc:check
  ```
- Stylelint for CSS/SCSS:
  ```shell
  yarn lint:css
  yarn lint:css:fix
  ```

## Production build & deployment

There is a helper script that automates a typical prod deployment workflow (Composer install no-dev, Node install, build assets, clear/warmup cache):

```shell
# On the target host (with PHP, Composer, Node/Yarn available)
./deploy.sh
# or via package.json
yarn deploy
```

Typical end-to-end steps:

```shell
# 1) Configure environment
export APP_ENV=prod
export APP_DEBUG=0
# Set APP_SECRET and DATABASE_URL as environment variables or via a prod .env.local

# 2) Install PHP deps (no-dev) and optimize autoloader
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 3) Install Node deps and build assets
yarn install --immutable || yarn install --frozen-lockfile
yarn build

# 4) Cache clear/warmup
php bin/console cache:clear --env=prod --no-debug --no-warmup
php bin/console cache:warmup --env=prod

# 5) Run DB migrations (if used)
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Web server configuration: point your virtual host to the `public/` directory. Example (Nginx, simplified):

```
server {
    server_name seepferdchen-garde.de;
    root /var/www/seepferdchen-garde/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

For immutable prod env files you can also use:

```shell
composer dump-env prod
```

## Docker notes

- Postgres and Mailpit services are provided via `compose.yaml` and `compose.override.yaml` for local development. Start what you need with `docker compose up -d <service>`.
- MariaDB examples are available via `docker-compose.mariadb.yml` (+ `.dev.yml` for port mapping).
- This repo does not include a full production container stack; deploy on a conventional PHP host or create your own Docker setup.

## Troubleshooting

- Missing APP_SECRET: run `php bin/console regenerate-app-secret` and set it in the environment.
- Assets not updating: ensure `yarn watch` is running or rebuild with `yarn build`.
- HMR not loading: dev server runs on http://localhost:8080; check CORS and that the Symfony server is running.
- Messenger transport errors: inspect with `php bin/console messenger:failed:show`.
- Database connection problems: verify `DATABASE_URL` (host/port/credentials) and serverVersion parameter for Doctrine.

## License & authors

- License: MIT (see `license` file)
- Authors:
    - Adam Ibrom <adam@net-i.de>
    - Riccardo Nappa <mail@seepferdchen-garde.de>

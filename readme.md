# Seepferdchen-Garde

**This is the official website of the Seepferdchen-Garde, a swimming school found by Riccardo Nappa.**

Website: [https://seepferdchen-garde.de/](https://seepferdchen-garde.de/)

## Get Started

To get this project you need to get a local copy of this repository first:

```shell
git clone git@github.com:net-idea/seepferdchen-garde.git seepferdchen-garde
cd seepferdchen-garde
```

Install the dependencies using Composer:

```shell
composer install
```

## Application Configuration

The application configuration is done via environment variables. You can copy the distribution file `.env.dist` to `.env` and adjust the values as needed:

```shell
cp .env.dist .env
```

Generate the application secret using the Symfony console command:

```shell
php bin/console regenerate-app-secret
```

Place the generated secret in the `.env` file to `APP_SECRET=` variable.

## Serve Application

This application is developed to be agnostic to the environment running on. For development the build in web server of PHP is sufficient:

```shell
php -S localhost:8000
```

## Development

How to run the development environment with hot module replacement (HMR) and file watchers for assets and templates:

Start the Node server in watch mode:

```shell
yarn run watch
```

Start Symfony server:

```shell
symfony server:start
```

_**Note:** Run the following commands in separate terminal windows._

## Code style & Testing

For code style checking we use PHP CS Fixer. To check the code style run:

```shell
vendor/bin/php-cs-fixer fix --diff --dry-run
```

For running the tests we use PHPUnit. To run the tests execute:

```shell
vendor/bin/phpunit
```

**Note:** You can also run both commands via the provided helper scripts:

```shell
./php-cs-fixer.sh
./phpunit.sh
```

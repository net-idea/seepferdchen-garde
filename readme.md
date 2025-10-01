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

### Database Setup

This application uses SQLite as database. The database file is located in `var/data.db`. To create the database schema run:

```shell
php bin/console doctrine:schema:update --force
```

### Reset the Database

To reset the database you can use the following command:

```shell
php bin/console doctrine:schema:drop --force
php bin/console doctrine:schema:update --force
```

Or the hard way recreate the database:

```shell
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
```

### Email Setup

Send test emails for various uses cases using the following commands:

```shell

php bin/console app:mail:test -vvv
php bin/console app:mail:preview-contact -vvv
php bin/console app:mail:preview-booking -vvv
php bin/console app:list:bookings -vvv
php bin/console app:list:contacts -vvv
```

To send real emails you need to configure the `MAILER_DSN` environment variable in your `.env` file.

If the messenger transport fails the failure que can be shown using:

```shell
php bin/console messenger:failed:show --env=prod
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
./lint.sh
./phpunit.sh
```

## OG Image Generation

Steps:

* Install sharp: `yarn add -D sharp`
* Run: `node generate-og.js`
* The images will be written to `public/assets/og/` and match the paths already used in `content/_pages.php`.

## Anmeldeformular PDF Generation

Das Anmeldeformular als PDF generieren:

```shell
./pdf.sh public/docs/2025.09-anmeldung-schwimmkurs-seepferdchen
```

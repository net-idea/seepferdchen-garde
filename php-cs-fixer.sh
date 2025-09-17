#!/bin/bash

mkdir -p php-cs-fixer
composer require --working-dir=php-cs-fixer friendsofphp/php-cs-fixer
php-cs-fixer/vendor/bin/php-cs-fixer fix

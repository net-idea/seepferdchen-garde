#!/bin/bash
# PHP CS Fixer is a dev dependency (composer.json); run it from vendor/bin.
"$(dirname "$0")/vendor/bin/php-cs-fixer" fix "$@"

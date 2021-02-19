#!/bin/bash

composer install

./bin/console doctrine:database:create
./bin/console doctrine:schema:create
./bin/console cache:clear
./bin/console cache:warmup
./bin/console doctrine:fixtures:load --no-interaction

php -d memory-limit=-1 -S "127.0.0.1:8000" -t "./public"

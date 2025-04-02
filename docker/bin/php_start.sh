#!/usr/bin/env bash

composer install

php artisan key:generate
php artisan storage:link
php artisan migrate --no-interaction
php artisan cms:install --no-interaction

sleep 10

php artisan key:generate --env=testing
php artisan migrate:fresh --env=testing

exec php-fpm --nodaemonize

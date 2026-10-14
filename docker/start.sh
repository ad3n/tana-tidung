#!/usr/bin/env bash
set -euo pipefail

cd /semart
mkdir -p var/cache var/log storage /var/log/supervisor

if [[ "${APP_ENV:-dev}" == "prod" ]]; then
    cp "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini"
    cp /etc/opcache.ini "${PHP_INI_DIR}/conf.d/opcache.ini"
    composer install --no-dev --prefer-dist --no-interaction --no-scripts --classmap-authoritative
else
    cp "${PHP_INI_DIR}/php.ini-development" "${PHP_INI_DIR}/php.ini"
    rm -f "${PHP_INI_DIR}/conf.d/opcache.ini"
    composer install --prefer-dist --no-interaction --no-scripts
fi

chown -R www-data:www-data var storage
su-exec www-data php bin/console cache:clear
php bin/console assets:install public
exec /usr/bin/supervisord -n -c /etc/supervisord.conf

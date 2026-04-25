#!/bin/sh
set -eu

mkdir -p /app/var/data /app/var/log /app/var/cache
chown -R www-data:www-data /app/var

if [ "${APP_ENV:-prod}" = "prod" ]; then
  su -s /bin/sh www-data -c "php bin/console cache:clear --no-warmup --env=prod" || true
  su -s /bin/sh www-data -c "php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration" || true
fi

exec "$@"

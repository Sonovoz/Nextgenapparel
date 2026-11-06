#!/bin/sh
# Arranque de los contenedores de la aplicación.
#   ROL=app     prepara la aplicación (migraciones) y arranca PHP-FPM.
#   ROL=cola    procesa la cola.
#   ROL=tareas  ejecuta las tareas programadas.
set -e

cd /var/www/html

# Espera a la base de datos (máximo 2 minutos).
php artisan nga:esperar-bd

if [ "${ROL:-app}" = "app" ]; then
    php artisan migrate --force
fi

php artisan optimize
php artisan filament:optimize > /dev/null 2>&1 || true

exec "$@"

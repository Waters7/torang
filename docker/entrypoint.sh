#!/bin/sh
# Konfigurasi di-cache saat kontainer dijalankan karena nilainya datang dari environment, bukan dari image.
# Hanya kontainer web yang menyiapkan route, view, dan cache Filament.
set -e

if [ "${TORANG_ROLE:-}" = "web" ]; then
    php artisan optimize --no-interaction
    php artisan filament:optimize --no-interaction
else
    php artisan config:cache --no-interaction
fi

exec "$@"

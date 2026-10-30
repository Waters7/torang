#!/usr/bin/env bash
# Deploy TORANG di VPS (SPEC §15). Jalankan dari folder aplikasi sebagai user pemilik kode
# (lihat README › Deployment VPS), mis.: sudo -iu torang /var/www/torang/deploy.sh
#
# Bila satu langkah gagal, skrip berhenti dan aplikasi tetap dalam mode maintenance.
# Perbaiki masalahnya lalu jalankan ulang skrip, atau buka kembali dengan `php artisan up`.

set -Eeuo pipefail

PHP="${PHP:-php}"

step() {
    printf '\n\033[1;36m==> %s\033[0m\n' "$1"
}

failed() {
    printf '\n\033[1;31mDeploy gagal pada langkah "%s". Aplikasi masih dalam mode maintenance.\033[0m\n' "$CURRENT_STEP" >&2
}

run_step() {
    CURRENT_STEP="$1"
    step "$1"
}

# Seluruh isi dibungkus fungsi supaya bash membaca skrip utuh sebelum `git pull` bisa mengubahnya.
main() {
    cd "$(dirname "$0")"
    trap failed ERR

    run_step "Mode maintenance"
    "$PHP" artisan down --retry=60

    run_step "Ambil kode terbaru"
    git pull --ff-only

    run_step "Dependensi PHP"
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

    run_step "Build aset frontend"
    npm ci --no-audit --no-fund
    npm run build

    run_step "Migrasi database"
    "$PHP" artisan migrate --force

    run_step "Sinkronisasi permission"
    "$PHP" artisan torang:sync-permissions

    run_step "Cache konfigurasi, route, view, event"
    "$PHP" artisan optimize
    "$PHP" artisan filament:optimize

    run_step "Restart worker queue"
    "$PHP" artisan queue:restart

    run_step "Buka aplikasi"
    "$PHP" artisan up

    trap - ERR

    step "Pemeriksaan kesiapan"
    "$PHP" artisan torang:check || printf '\n\033[1;33mAda pemeriksaan yang gagal — lihat tabel di atas.\033[0m\n'
}

main "$@"

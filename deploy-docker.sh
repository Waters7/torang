#!/usr/bin/env bash
# Pembaruan TORANG di server Docker. Jalankan dari folder aplikasi: ./deploy-docker.sh
# Bila satu langkah gagal, skrip berhenti dan aplikasi tetap dalam mode maintenance.
# Perbaiki masalahnya lalu jalankan ulang, atau buka kembali dengan:
#   docker compose exec app php artisan up

set -Eeuo pipefail

step() {
    printf '\n\033[1;36m==> %s\033[0m\n' "$1"
}

failed() {
    printf '\n\033[1;31mDeploy gagal pada langkah "%s".\033[0m\n' "$CURRENT_STEP" >&2
}

run_step() {
    CURRENT_STEP="$1"
    step "$1"
}

# Seluruh isi dibungkus fungsi supaya bash membaca skrip utuh sebelum `git pull` bisa mengubahnya.
main() {
    cd "$(dirname "$0")"
    trap failed ERR

    run_step "Ambil kode terbaru"
    git pull --ff-only

    run_step "Build image"
    docker compose build

    run_step "Database"
    docker compose up -d --wait db

    run_step "Mode maintenance"
    docker compose run --rm -T app php artisan down --retry=60

    run_step "Migrasi database"
    docker compose run --rm -T app php artisan migrate --force

    run_step "Sinkronisasi permission"
    docker compose run --rm -T app php artisan torang:sync-permissions

    run_step "Jalankan kontainer baru"
    docker compose up -d --remove-orphans

    run_step "Restart worker queue"
    docker compose exec -T app php artisan queue:restart

    run_step "Buka aplikasi"
    docker compose exec -T app php artisan up

    trap - ERR

    step "Pemeriksaan kesiapan"
    docker compose exec -T app php artisan torang:check || printf '\n\033[1;33mAda pemeriksaan yang gagal, lihat tabel di atas.\033[0m\n'
}

main "$@"

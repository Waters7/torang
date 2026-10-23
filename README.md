# TORANG

**T**ata **O**perasional **R**uang CBT, **A**plikasi, **N**etwork & **G**angguan — sistem layanan IT
UPT TIK Poltekkes Kemenkes Manado.

- Spesifikasi: [docs/SPEC.md](docs/SPEC.md)
- Progres per fase: [docs/PROGRESS.md](docs/PROGRESS.md)
- Keputusan teknis: [docs/DECISIONS.md](docs/DECISIONS.md)

## Menjalankan di lokal

Kebutuhan: PHP 8.3+ (ekstensi zip, xml, gd, intl, mbstring, pdo_mysql), Composer, Node.js LTS,
MySQL 8 / MariaDB 10.6+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Isi `DB_*`, `ADMIN_EMAIL`, dan `ADMIN_PASSWORD` di `.env`, buat database-nya, lalu:

```bash
php artisan migrate:fresh --seed
npm run build
composer run dev
```

- Beranda publik: http://localhost:8000
- Panel admin: http://localhost:8000/admin (login dengan `ADMIN_EMAIL`/`ADMIN_PASSWORD`)

## Perintah

| Perintah | Kegunaan |
|---|---|
| `composer run dev` | server + queue + Vite |
| `php artisan test` | seluruh test (Pest) |
| `./vendor/bin/pint` | format kode PHP |
| `npx tsc --noEmit` | cek tipe TypeScript |
| `npm run build` | build aset frontend |

Panduan deployment VPS menyusul di Fase 8.

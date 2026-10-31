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
| `php artisan torang:check` | periksa kesiapan server (konfigurasi, database, ping, LibreOffice, queue, scheduler) |
| `php artisan torang:sync-permissions` | tambahkan permission baru ke role tanpa menimpa perubahan di panel |

## Deployment VPS (Ubuntu 24.04 LTS)

TORANG butuh VPS, bukan shared hosting: monitoring menjalankan `ping`, worker queue, dan cron.
Sebaiknya server berada **di luar jaringan kampus** supaya tetap bisa mendeteksi dan mengirim
notifikasi ketika jaringan kampus putus. Contoh di bawah memakai domain `torang.example.ac.id`,
folder `/var/www/torang`, dan user deploy `torang` — ganti sesuai kebutuhan.

Contoh konfigurasi siap salin ada di folder [`deploy/`](deploy):

| File | Tujuan |
|---|---|
| [`deploy/nginx/torang.conf`](deploy/nginx/torang.conf) | `/etc/nginx/sites-available/torang` |
| [`deploy/supervisor/torang.conf`](deploy/supervisor/torang.conf) | `/etc/supervisor/conf.d/torang.conf` |
| [`deploy/cron/torang`](deploy/cron/torang) | `/etc/cron.d/torang` |
| [`deploy/sysctl/99-torang-ping.conf`](deploy/sysctl/99-torang-ping.conf) | `/etc/sysctl.d/` (hanya bila ping gagal) |
| [`deploy.sh`](deploy.sh) | skrip pembaruan aplikasi |

### 1. Paket sistem

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip acl supervisor iputils-ping \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring php8.3-zip php8.3-gd \
  php8.3-intl php8.3-curl php8.3-bcmath \
  libreoffice-writer fonts-liberation fonts-crosextra-carlito \
  certbot python3-certbot-nginx
```

- `mysql-server` sudah menyertakan `mysqldump` untuk backup harian. Bila memakai MariaDB,
  pasang `mariadb-server` (berisi `mariadb-dump`).
- `libreoffice-writer` + font dipakai untuk konversi laporan UKOM ke PDF. Opsional:
  `ttf-mscorefonts-installer` agar Arial/Calibri identik dengan Word. Tanpa LibreOffice,
  laporan UKOM tetap bisa diunduh dalam format Word.
- Composer: ikuti petunjuk di [getcomposer.org/download](https://getcomposer.org/download/), lalu
  pindahkan ke `/usr/local/bin/composer`.
- Node.js 22 LTS (minimal 22.12 untuk Vite 8), mis. dari NodeSource:

  ```bash
  curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
  sudo apt install -y nodejs
  ```

Atur batas unggah PHP-FPM (lampiran PDF 2 MB, template .docx, unggahan sementara Livewire) di
`/etc/php/8.3/fpm/conf.d/99-torang.ini`:

```ini
upload_max_filesize = 16M
post_max_size = 20M
memory_limit = 256M
expose_php = Off
```

### 2. Database

```bash
sudo mysql
```

```sql
CREATE DATABASE torang CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'torang'@'localhost' IDENTIFIED BY 'ganti-dengan-password-kuat';
GRANT ALL PRIVILEGES ON torang.* TO 'torang'@'localhost';
FLUSH PRIVILEGES;
```

Port MySQL tidak perlu dibuka ke luar.

### 3. User deploy dan kode

Kode dimiliki user `torang`; PHP-FPM, worker, dan cron berjalan sebagai `www-data` yang hanya
boleh menulis ke `storage/` dan `bootstrap/cache/`.

```bash
sudo adduser --disabled-password --gecos "" torang
sudo mkdir -p /var/www/torang && sudo chown torang:torang /var/www/torang
sudo -iu torang git clone https://github.com/Waters7/torang.git /var/www/torang

cd /var/www/torang
sudo setfacl -R -m u:www-data:rwX -m u:torang:rwX storage bootstrap/cache
sudo setfacl -dR -m u:www-data:rwX -m u:torang:rwX storage bootstrap/cache
```

### 4. Konfigurasi `.env`

```bash
sudo -iu torang
cd /var/www/torang
cp .env.example .env
php artisan key:generate
nano .env
```

Nilai yang wajib diubah untuk produksi (lihat komentar "Produksi:" di `.env.example`):

| Kunci | Nilai produksi |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://torang.example.ac.id` |
| `SESSION_SECURE_COOKIE` | `true` |
| `DB_*` | database dan user dari langkah 2 |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `warning` |
| `MAIL_*` | SMTP email kampus — dipakai notifikasi dan email **lupa kata sandi** petugas |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | akun super admin awal (kosongkan password setelah login pertama) |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | kunci Cloudflare Turnstile asli (domain TORANG didaftarkan di dasbor Cloudflare) — wajib untuk form publik dan halaman masuk petugas; bila kosong di produksi keduanya menolak kiriman |
| `TELEGRAM_BOT_TOKEN` | token bot dari @BotFather (opsional) |
| `WHATSAPP_GATEWAY_URL`, `WHATSAPP_GATEWAY_TOKEN` | gateway WhatsApp (opsional, nonaktif secara default) — kabar otomatis ke pemohon Lab CBT dan pelapor tiket; tanpa gateway petugas tetap bisa memakai tombol "Chat WhatsApp pelapor" |
| `TRUSTED_PROXIES` | isi hanya bila memakai reverse proxy/CDN (mis. Cloudflare) |

`.env` berisi rahasia: jangan di-commit dan batasi aksesnya:
`sudo chown torang:www-data .env && sudo chmod 640 .env`.

### 5. Instalasi pertama

Masih sebagai user `torang`:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
php artisan filament:optimize
```

Di produksi, seeder hanya membuat role & permission, akun super admin, pengaturan default,
departemen, lab, shift, provider hosting, dan Kolegium TPD — tanpa data contoh.

### 6. Nginx dan HTTPS

```bash
sudo certbot certonly --nginx -d torang.example.ac.id
sudo cp /var/www/torang/deploy/nginx/torang.conf /etc/nginx/sites-available/torang
sudo nano /etc/nginx/sites-available/torang   # ganti domain dan path
sudo ln -s /etc/nginx/sites-available/torang /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx php8.3-fpm
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

Aplikasi sendiri mengirim header keamanan (`X-Frame-Options`, `X-Content-Type-Options`,
`Referrer-Policy`, `Permissions-Policy`, dan HSTS di HTTPS produksi).

### 7. Worker queue, scheduler, dan ping

```bash
sudo cp /var/www/torang/deploy/supervisor/torang.conf /etc/supervisor/conf.d/torang.conf
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status

sudo cp /var/www/torang/deploy/cron/torang /etc/cron.d/torang
sudo chmod 644 /etc/cron.d/torang
```

- Worker `monitoring` (3 proses) menjalankan cek jaringan & aplikasi secara paralel;
  worker `default` menangani email, notifikasi, konversi PDF, dan backup.
- Cron memanggil `schedule:run` tiap menit: monitoring, pengingat, agregasi harian, pruning log,
  dan backup database (02:00, disimpan 14 hari).
- Uji ping sebagai `www-data`: `sudo -u www-data ping -c 1 8.8.8.8`. Bila ditolak, pasang
  `deploy/sysctl/99-torang-ping.conf` ke `/etc/sysctl.d/` lalu `sudo sysctl --system`.

### 8. Periksa kesiapan

```bash
sudo -u www-data php /var/www/torang/artisan torang:check
```

Perintah ini hanya membaca keadaan dan menampilkan tabel OK / PERINGATAN / GAGAL untuk
APP_KEY, APP_DEBUG, HTTPS, database & migrasi, akun super admin, folder storage, ping,
LibreOffice, `mysqldump`, scheduler (tunggu 1–2 menit setelah cron dipasang), worker queue,
job gagal, captcha, email, dan Telegram. Kode keluar bukan nol bila ada yang GAGAL.

Setelah itu login ke `/admin`, ganti password super admin, lalu lengkapi
**Sistem › Pengaturan umum** (identitas instansi, Kepala Unit, kontak helpdesk),
**Monitoring › Pengaturan** (channel notifikasi, chat ID grup Telegram), dan data lab, monitor,
serta petugas.

### Pembaruan aplikasi

```bash
sudo -iu torang /var/www/torang/deploy.sh
```

Skrip ini menjalankan: mode maintenance → `git pull --ff-only` → `composer install --no-dev -o` →
`npm ci && npm run build` → `migrate --force` → `torang:sync-permissions` → `optimize` &
`filament:optimize` → `queue:restart` → aplikasi dibuka kembali → `torang:check`.
Bila satu langkah gagal, skrip berhenti dan aplikasi tetap dalam mode maintenance; perbaiki lalu
jalankan ulang, atau buka manual dengan `php artisan up`.

`torang:sync-permissions` hanya menambahkan permission baru ke role bawaan, sehingga perubahan hak
akses yang dibuat di panel tetap dipertahankan. Pakai `--reset` untuk mengembalikan semua role ke
bawaan.

### Backup

Backup database berjalan otomatis pukul 02:00 WITA ke `storage/app/private/backups`
(disimpan 14 hari) dan bisa dibuat/diunduh manual dari **Sistem › Backup database**. Salin backup secara
berkala ke lokasi lain (mis. penyimpanan kampus), karena backup di VPS yang sama tidak melindungi
dari kerusakan server. Isi `BACKUP_ARCHIVE_PASSWORD` bila arsip ingin dienkripsi.

### Pemecahan masalah

| Gejala | Periksa |
|---|---|
| Halaman 500 setelah ubah `.env` | jalankan `php artisan optimize` lagi (konfigurasi di-cache) |
| Monitor tetap "Tidak diketahui" | `supervisorctl status`, `storage/logs/worker-monitoring.log`, baris Scheduler di `torang:check` |
| Monitor jaringan selalu Offline | ping sebagai `www-data` (langkah 7) |
| Tombol PDF laporan UKOM nonaktif | `soffice` belum terpasang atau `LIBREOFFICE_BINARY` salah |
| Form publik atau halaman masuk selalu ditolak captcha | kunci Turnstile dan domain di dasbor Cloudflare |
| Email lupa kata sandi tidak sampai | `MAIL_*`, worker `default` (`supervisorctl status`), folder spam penerima |
| IP pengunjung salah di log/rate limit | `TRUSTED_PROXIES` bila memakai proxy/CDN |

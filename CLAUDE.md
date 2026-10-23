# TORANG — Sistem Layanan IT Poltekkes Kemenkes Manado

**TORANG** = **T**ata **O**perasional **R**uang CBT, **A**plikasi, **N**etwork & **G**angguan.

Aplikasi web Laravel untuk UPT TIK Poltekkes Kemenkes Manado: penjadwalan & pengajuan
Lab CBT, jadwal petugas IT mingguan, monitoring jaringan internet per jurusan dan aplikasi
kampus, manajemen SSID/password WiFi, pengingat hosting/VPS/domain, serta helpdesk.

Spesifikasi lengkap ada di @docs/SPEC.md — **baca sebelum mengerjakan fitur apa pun.**

## Stack
- Laravel versi stabil terbaru, PHP 8.3+, MySQL 8 / MariaDB 10.6+
- Panel admin: Filament (versi stabil terbaru yang kompatibel), di `/admin`
- Halaman publik: **React + TypeScript** via **Inertia.js** (inertiajs/inertia-laravel + @inertiajs/react),
  Vite, Tailwind CSS. Halaman di `resources/js/pages/public/*`, komponen di `resources/js/components/*`.
- Queue: database (lokal), Redis (produksi, opsional) · Scheduler Laravel
- Paket PHP: spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-backup,
  maatwebsite/excel, barryvdh/laravel-dompdf, phpoffice/phpword,
  laravel-notification-channels/telegram, pestphp/pest
- Deploy: VPS Ubuntu (Nginx + PHP-FPM + MySQL + Node LTS untuk build + LibreOffice untuk konversi PDF)
- Font: Sora (UI & judul), JetBrains Mono (angka, kode, label teknis) — Google Fonts

## Perintah
- `composer run dev` — server + vite + queue lokal
- `php artisan migrate:fresh --seed` — reset DB dengan data contoh
- `php artisan test` — jalankan seluruh test (Pest)
- `./vendor/bin/pint` — format kode PHP
- `npm run dev` / `npm run build` — aset frontend (React)
- `npx tsc --noEmit` — cek tipe TypeScript
- `php artisan schedule:work` — jalankan scheduler lokal
- `php artisan queue:work --queue=monitoring,default`

## Konvensi
- Kode (class, method, tabel, kolom, route name, enum case) dalam **bahasa Inggris**.
  Semua teks UI, label, pesan validasi, notifikasi, dan PDF dalam **bahasa Indonesia**.
- `APP_TIMEZONE=Asia/Makassar`, `APP_LOCALE=id`, Carbon locale `id`. Tampilkan jam dengan akhiran "WITA".
- Status memakai PHP backed enum (mis. `BookingStatus`, `MonitorStatus`) dengan method `label()` dan `color()`.
- Logika bisnis di `app/Actions` atau `app/Services`, bukan di controller/Livewire/Filament resource.
- Otorisasi lewat Policy + spatie/permission. Jangan cek role dengan string tersebar di view.
- Setiap fitur wajib punya Pest feature test: happy path, validasi, dan otorisasi.
- Format angka & tanggal Indonesia: `99,9%`, `22 Oktober 2026`, `Rp1.250.000`
  (khusus dokumen laporan UKOM: `Rp 280.000,00`, lihat SPEC §8A).
- React: function component + hooks, props bertipe (TypeScript) dari data Inertia; tanpa state global
  kecuali perlu. Data publik dikirim lewat Inertia props/JSON yang sudah disaring di server
  (pakai API Resource), jangan mengirim model mentah ke frontend.
- Template dokumen Word ada di `resources/templates/`. Jangan ubah placeholder `${...}` tanpa
  memperbarui service dan test terkait.

## Aturan keamanan (wajib)
- Perintah ping **selalu** lewat `Symfony\Component\Process\Process` dengan argumen array.
  Jangan pernah membangun string shell. Validasi host dengan `FILTER_VALIDATE_IP` atau regex hostname.
- Password WiFi memakai `encrypted` cast, tidak pernah di-log, di-cache, atau dikirim ke halaman publik.
- Halaman publik **tidak boleh** menampilkan IP, SSID, password, akun hosting, atau data pribadi pemohon.
- Upload: validasi `mimes:pdf|max:2048`, simpan di disk privat, unduh lewat route ber-otorisasi.
- Endpoint publik diberi rate limit, dan form publik memakai captcha + honeypot.

## Cara kerja
- Kerjakan **per fase** sesuai docs/SPEC.md §14. Di awal setiap fase, buat rencana singkat
  lalu tunggu persetujuan. Setelah implementasi: jalankan `php artisan test` dan `pint`,
  centang progres di `docs/PROGRESS.md`, commit dengan pesan jelas, lalu **berhenti untuk review**.
- Keputusan teknis yang tidak diatur SPEC: pilih default yang wajar, catat di `docs/DECISIONS.md`.
- Jangan menambah fitur di luar SPEC tanpa bertanya.
- Inventaris aset & peminjaman barang **di luar ruang lingkup** (urusan unit BMN).

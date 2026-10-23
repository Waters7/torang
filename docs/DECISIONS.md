# Keputusan Teknis TORANG

Catatan keputusan yang tidak diatur langsung oleh `docs/SPEC.md`. Tambahkan entri baru di bawah
fase terkait.

## Fase 0 — Fondasi (23 Oktober 2026)

### Versi
| Komponen | Versi terpasang |
|---|---|
| Laravel | 13 (skeleton 13.11, framework ^13.17) |
| Filament | 5.10 (Livewire 4) |
| Inertia | inertia-laravel 3.5, @inertiajs/react 3.9 |
| Frontend | React 19, TypeScript 7, Vite 8, Tailwind CSS 4, @heroicons/react |
| Test | Pest 4.7 + pest-plugin-laravel |
| Paket lain | spatie/laravel-permission 8.3, spatie/laravel-activitylog 4.12, spatie/laravel-backup 10.3, maatwebsite/excel 4.0, barryvdh/laravel-dompdf 3.1, phpoffice/phpword 1.4, laravel-notification-channels/telegram 8.0 |

- `composer.json` mengunci `config.platform.php = 8.3.0` supaya dependensi tetap cocok dengan
  PHP-FPM 8.3 di VPS, walaupun lokal memakai PHP 8.4 (Laragon).

### Struktur & instalasi
- Skeleton Laravel 13 membawa `CLAUDE.md`/`AGENTS.md` berisi bootstrap Laravel Boost. Keduanya tidak
  disalin; `CLAUDE.md` proyek tetap dipakai.
- Inertia + React dipasang manual, **bukan** React starter kit Laravel: login admin sudah ditangani
  Filament, jadi halaman auth React dari starter kit tidak dibutuhkan.
- SSR Inertia tidak diaktifkan (tanpa bundle SSR, Inertia otomatis render di klien).
- Font Sora & JetBrains Mono dimuat dari Google Fonts: `<link>` di `resources/views/app.blade.php`
  (publik) dan `GoogleFontProvider` di panel Filament. Konfigurasi font Bunny bawaan skeleton dihapus.
- Pint mengecualikan `docs/` (`pint.json`) agar file referensi tidak ikut diformat ulang.

### Pengaturan (`SettingsService`)
- Tabel `settings` (`key` unik, `value` JSON). **Default ada di `config/settings.php`** sebagai satu
  sumber kebenaran; `SettingsService::get()` memakai default ini bila key belum tersimpan.
- Semua nilai tersimpan di-cache utuh (`settings.all`, `rememberForever`) dan cache dihapus setiap
  `set()`/`setMany()`.
- `SettingsSeeder` hanya menambah key yang belum ada, tidak menimpa nilai yang sudah diubah admin.
- Cooldown notifikasi disimpan sekali sebagai `notifications.cooldown_minutes` (dipakai juga oleh
  monitoring), meski SPEC menyebutnya di §5.2 dan §13.
- Interval monitoring default 1 menit (mengikuti contoh label "PING TIAP 1 MENIT" di §11.2).
- URL & token gateway WhatsApp serta token bot Telegram ada di `.env` → `config/services.php`,
  bukan di tabel `settings`.
- Data penanda tangan laporan UKOM (`reports.ukom.signer_name`/`signer_nip`) di-seed kosong dan diisi
  lewat Pengaturan, tidak di-hardcode.

### Role, permission & akses panel
- Role dan permission didefinisikan sebagai enum `App\Enums\Role` dan `App\Enums\Permission`.
  Pemetaan role → permission ada di `Role::permissions()`; seeder menyinkronkannya.
- `super_admin` diberi **semua** permission secara eksplisit (tanpa `Gate::before`), jadi pengecekan
  selalu lewat permission.
- Daftar permission untuk semua modul (Fase 1–7) sudah dibuat di Fase 0 agar seeder stabil.
  Ringkasan pemetaan:

  | Role | Permission |
  |---|---|
  | `kepala_unit` | lihat lab/pengajuan, kelola & **approve** pengajuan, kelola & terbitkan jadwal, kelola petugas, lihat monitoring, lihat WiFi + reveal password, lihat hosting & tiket, kelola pengumuman, laporan & laporan UKOM, audit log |
  | `petugas_it` | lihat lab/pengajuan, log pemakaian, Jadwal Saya, kelola monitoring, WiFi penuh, hosting penuh, tiket penuh |
  | `pengelola_cbt` | kelola lab, kelola pengajuan (tanpa approve), log pemakaian, Jadwal Saya, laporan & laporan UKOM |

- Siapa yang boleh approve tetap mengikuti setting `bookings.approver_roles` (dipakai di Fase 1)
  di samping permission `bookings.approve`.
- Akses panel `/admin`: `User::canAccessPanel()` = akun aktif **dan** punya salah satu dari 4 role.
- Kolom `users.is_active` default `true` di database dan di model (`$attributes`).
- `AdminUserSeeder` memakai `firstOrCreate`: password admin yang sudah ada tidak ditimpa saat seeding ulang.
- `RolesAndPermissionsSeeder` mereset cache permission secara manual karena `DatabaseSeeder` memakai
  `WithoutModelEvents` (event yang biasanya membersihkan cache spatie tidak berjalan).

### Tampilan
- Tema Filament cukup lewat `->colors()` (primary `#08796C`; success/warning/danger/info = warna status
  §11.1), tanpa file tema CSS kustom. Wordmark panel (`resources/views/filament/brand.blade.php`)
  memakai style inline karena CSS Filament sudah dikompilasi. Tema Vite kustom bisa ditambah bila
  nanti view Filament butuh utilitas Tailwind sendiri.
- Token warna, font, dan radius ada di `@theme` pada `resources/css/app.css` (Tailwind 4, CSS variables).
- `favicon.ico` (16/32/48 px) dan `apple-touch-icon.png` (180 px) digambar dari geometri yang sama
  dengan `public/favicon.svg` (simbol diskalakan 0,875 dengan padding 22 pada viewBox 128).
- Di mobile, menu header turun ke baris baru dan membungkus (wrap), bukan digeser horizontal.
- Tautan header/footer ke `/ajukan`, `/jadwal`, `/cek-status`, `/status`, `/lapor` sudah dipasang;
  route-nya dibuat di fase masing-masing (sampai saat itu masih 404).

### Bahasa & waktu
- `config/app.php` membaca `APP_TIMEZONE` (default `Asia/Makassar`); `APP_LOCALE=id`,
  `APP_FAKER_LOCALE=id_ID`, dan `Carbon::setLocale()` di `AppServiceProvider`.
- Terjemahan `lang/id/{validation,auth,passwords,pagination}.php` ditulis sendiri, tanpa paket tambahan.

### Test
- Pest memakai SQLite in-memory (`phpunit.xml`); MySQL untuk lokal & produksi.
- `Tests\TestCase` memanggil `withoutVite()` supaya test tidak bergantung pada `npm run build`.

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

## Fase 1 — Lab CBT & Pengajuan (23 Oktober 2026)

### Paket
- Kalender admin memakai `saade/filament-fullcalendar` **v4.0.1** (versi stabil yang mendukung
  Filament 5 dan Laravel 13; seri 5.x masih beta). Widget kalender tidak lazy-load dan tidak
  tampil di dashboard (`isDiscovered()` = `false`). Klik event membuka detail pengajuan.
  Pengajuan menunggu tampil berlatar putih dengan garis warna lab; yang ditolak/dibatalkan tidak tampil.
- Captcha: **Cloudflare Turnstile**, diverifikasi di server lewat HTTP client tanpa paket tambahan
  (`TurnstileVerifier`). Tanpa secret key verifikasi selalu gagal (fail closed). `.env` lokal memakai
  kunci uji resmi Cloudflare; test memalsukan respons HTTP.

### Pengajuan
- Kode `CBT-YYMM-XXXX`: YYMM = bulan pengajuan dibuat, XXXX acak dari 32 karakter tanpa I/O/0/1
  (bukan nomor urut) agar kode orang lain tidak bisa ditebak di `/cek-status`.
- Jam disimpan `H:i:s` dan dibaca `H:i`; query tanggal memakai `whereDate` agar sama di MySQL & SQLite.
- Cek bentrok dijalankan di dalam transaksi dengan `lockForUpdate` pada baris lab.
- Pengajuan dari panel admin: bebas aturan H-3, tetap dicek bentrok, jam operasional, dan kapasitas
  (kecuali jenis maintenance). Bila pembuatnya penyetuju, pengajuan langsung disetujui.
- Approve mengecek ulang semua aturan (lab bisa saja masuk maintenance setelah pengajuan dibuat).
- Ubah data hanya untuk pengajuan menunggu/disetujui; hapus (soft delete) hanya untuk yang sudah tidak
  aktif. Lab yang punya riwayat pengajuan tidak bisa dihapus — ubah statusnya menjadi nonaktif.
- Log pemakaian baru bisa diisi setelah kegiatan dimulai, dan otomatis menandai pengajuan selesai.

### Akses & data publik
- Master Departemen/Unit masuk grup **Sistem** dengan permission baru `departments.manage`
  (super_admin, kepala_unit).
- Halaman publik (terkirim, cek status) memakai `PublicBookingResource` tanpa nama, NIP/NIM, email,
  dan nomor WhatsApp pemohon. Alasan penolakan tetap ditampilkan agar pemohon tahu sebabnya.
- API ketersediaan hanya mengembalikan jam terpakai, tanpa nama kegiatan.
- `/cek-status` diberi `throttle:30,1` (tidak ada di tabel SPEC §9.1) untuk mencegah tebak kode.
- Lampiran disimpan di `storage/app/private/bookings/{kode}/surat-permohonan.pdf`, diunduh lewat
  `/admin/lampiran/pengajuan/{booking}` dengan policy. Tamu diarahkan ke login Filament.
- Foto lab disimpan di disk `local` (privat, `serve` aktif) sehingga tidak perlu `storage:link`.

### Notifikasi & penjadwal
- Email ke pemohon selalu dikirim (on-demand, lewat queue). Telegram grup hanya bila token bot,
  chat ID, dan channel `telegram` di pengaturan aktif. Kanal WhatsApp menyusul di Fase 3.
- Teks template email Laravel diterjemahkan di `lang/id.json`.
- Tabel `reminder_logs` dibuat di Fase 1 karena pengingat H-1 pengajuan sudah memakainya.

### Seeder
- Departemen dan lab di-seed di semua lingkungan (`updateOrCreate`); contoh pengajuan hanya di
  luar produksi.

### Lingkungan lokal
- `php.ini` PHP 8.4 di Laragon belum menyetel `curl.cainfo`, sehingga verifikasi Turnstile gagal
  dengan "cURL error 60". Perbaikannya: isi `curl.cainfo` dan `openssl.cafile` dengan
  `C:\laragon\etc\ssl\cacert.pem` (seperti `php.ini` PHP 8.3). Di VPS Ubuntu tidak perlu.

## Fase 2 — Jadwal Petugas (23 Oktober 2026)

### Data
- `duty_assignments.lab_id` kosong = seluruh CBT Center. Karena NULL tidak dianggap sama di index unik,
  keunikan tanggal × shift × cakupan × petugas memakai kolom turunan `scope_key = COALESCE(lab_id, 0)`.
  MySQL tidak mengizinkan cascade/set null pada FK yang dipakai kolom turunan, jadi FK `lab_id`
  memakai restrict; `LabPolicy` juga menolak hapus lab yang punya penugasan jaga.
- Kolom jam (`Booking`, `Shift`) memakai trait bersama `HasTimeColumns` (disimpan H:i:s, dibaca H:i).
- Hari jadwal tetap Senin–Sabtu (SPEC §4); setting `bookings.show_sunday` hanya untuk tampilan publik.
- Petugas/shift yang sudah punya riwayat tugas tidak bisa dihapus — dinonaktifkan saja.

### Grid & penerbitan
- Grid disimpan utuh per minggu (semua cakupan sekaligus, isi lama diganti) lewat `SaveDutyGrid`.
  Tab cakupan berisi Seluruh CBT Center dan semua lab (termasuk yang maintenance) agar penugasan
  lab tidak hilang saat disimpan.
- "Salin dari minggu lalu" mengganti isi draf, menggeser tanggal +7 hari, dan melewati petugas/shift nonaktif.
- Jadwal terbit dikunci; "Kembalikan ke draf" mempertahankan `published_at` sehingga penerbitan
  berikutnya dikirim sebagai revisi ke semua petugas yang bertugas.
- Peringatan slot kosong hanya untuk pengajuan berstatus disetujui; petugas "seluruh CBT Center"
  dianggap menutup semua lab. Kegiatan di luar jam semua shift juga diperingatkan. Tidak memblokir terbit.
- Rekap bulanan dan Jadwal Saya hanya menghitung jadwal yang sudah terbit. Rekap dibuka dari tombol di
  Jadwal Mingguan (tidak menambah menu, menu Petugas tetap sesuai SPEC §10).

### Notifikasi & PDF
- Notifikasi jadwal hanya lewat Telegram (petugas tanpa chat ID dilewati); tanpa cadangan email,
  sesuai keputusan saat persetujuan rencana. WhatsApp menyusul di Fase 3.
- Pengingat H-1 tercatat di `reminder_logs` per petugas (`duty:{tanggal}`).
- PDF dibuat `DutyWeekPdf` (DomPDF) dengan font subsetting aktif (±24 KB, bukan ±880 KB);
  draf diberi tanda "DRAF — BELUM DITERBITKAN".

### Hak akses
- Grid & jadwal: `duty.manage`; terbit/kembalikan ke draf: `duty.publish`; master petugas & shift:
  `staff.manage`; Jadwal Saya: `duty.view-own` (kepala unit, petugas IT, pengelola CBT).

## Tambahan — Master Pegawai dari DUK (23 Oktober 2026)

- Fitur di luar SPEC, disetujui pengguna sebelum Fase 3.
- Tabel `employees` menyimpan **minimal**: NIP (unik, 18 digit), nama, golongan, jabatan, jabatan
  struktural, penempatan + `department_id`, email Kemenkes, status aktif, periode DUK terakhir.
  NIK, tempat/tanggal lahir, telepon, dan email pribadi sengaja tidak disimpan (tidak dibutuhkan TORANG).
- `DukImporter` membaca hanya sheet "LAPORAN DUK" dengan pembaca Xlsx eksplisit. Kolom dikenali dari teks
  header dua baris ("Nama", "NIP Baru", sub-header "Golongan", "Jabatan", "Jabatan Struktural…",
  "Penempatan…", sub-header "e-mail Kemenkes"), bukan posisi kolom.
- Penempatan DUK dipetakan ke departemen dengan mencocokkan nama/nama singkat (tanpa awalan "Jurusan",
  "Direktorat / Unit" → Direktorat). Penempatan "Pensiun" → nonaktif.
- Impor ulang: upsert berdasarkan NIP; pegawai aktif yang tidak ada di file terbaru dinonaktifkan
  (tidak dihapus). Baris dengan NIP tidak 18 digit atau ganda dilewati dan dilaporkan.
- Master pegawai hanya baca di panel (permission baru `employees.manage`: super_admin, kepala_unit);
  perubahan data lewat impor DUK berikutnya.
- File unggahan disimpan sementara di disk privat lalu dihapus setelah diproses (berhasil maupun gagal).
- Repo GitHub publik: file DUK dan data pegawai asli tidak pernah di-commit atau di-seed. `*.xlsx`/`*.xls`
  ditambahkan ke `.gitignore`; test memakai file DUK sintetis (`tests/Support/DukFile.php`).
- `staff.employee_id` (opsional, unik) menghubungkan petugas ke pegawai; tenaga non-PNS tetap bisa
  dicatat sebagai petugas tanpa data pegawai.
- Penanda tangan laporan UKOM (Fase 6) akan memakai master pegawai yang sama.

## Fase 3 — Mesin Monitoring (23 Oktober 2026)

### Status & insiden
- Gagal di bawah batas gagal berturut-turut dicatat sebagai **Lambat** (status cek dan status monitor),
  dengan keterangan "Gagal n/N: …", agar gangguan sesaat tidak langsung merah di bar riwayat & uptime.
  Offline baru ditetapkan setelah N gagal; selama sudah Offline, gagal berikutnya tetap Offline.
- Insiden dimulai dari waktu cek gagal pertama pada rangkaian itu, bukan saat ambang tercapai.
- Uptime = (Online + Lambat) / total cek.
- Monitor nonaktif otomatis "Dijeda"; saat diaktifkan lagi mulai dari "Tidak diketahui".
- `monitor_incidents` ditambah `down_notified_at`/`up_notified_at` (tidak ada di §12) untuk cooldown:
  notifikasi Offline ditahan bila notifikasi Offline monitor yang sama terkirim dalam masa cooldown,
  dan notifikasi pulih hanya dikirim bila notifikasi Offline insiden tersebut terkirim.
- `monitors` ditambah `last_error` dan `degraded_notified_at` (notifikasi Lambat sekali per periode Lambat).

### Pengecekan
- Ambang Lambat global berbeda per grup: jaringan `monitoring.latency_warning_ms` (150 ms),
  aplikasi `monitoring.response_warning_ms` (2000 ms); `latency_warning_ms` per monitor menimpa keduanya.
- HTTP tidak mengikuti redirect agar 301/302 dinilai sesuai daftar kode normal (default 200, 301, 302).
- Host divalidasi `MonitorHost` (IP via FILTER_VALIDATE_IP atau regex hostname; menolak spasi, karakter shell,
  awalan "-") di form dan diulang di checker sebelum `ping` dijalankan.
- `CheckMonitorJob` unik per monitor (ShouldBeUnique, 5 menit) supaya tidak menumpuk saat queue lambat.
- Peringatan SSL: satu kali per ambang per tanggal kedaluwarsa (`reminder_logs` key `ssl:{tanggal}:{ambang}`),
  sehingga perpanjangan sertifikat otomatis memulai siklus baru.
- Grafik 24 jam memakai rata-rata per jam dari `monitor_checks`; 7/30 hari dari `monitor_daily_stats`.
- Widget grafik dan riwayat insiden di halaman detail tidak lazy-load (isi utama halaman).

### Notifikasi
- Email monitoring ke PIC monitor; bila PIC kosong, ke semua pengguna aktif dengan `monitors.manage`.
- Kanal ditentukan `NotificationChannels`: aktif di Pengaturan **dan** kredensial ada di `.env`.
  Email ke pemohon pengajuan tetap selalu dikirim.
- Kanal WhatsApp: custom channel `whatsapp`, gateway HTTP generik (POST JSON `target` + `message`,
  header `Authorization: <token>`), nomor dinormalkan ke 62…; driver `log` untuk lokal. Dipakai untuk pemohon
  pengajuan dan petugas jaga. Format bisa disesuaikan bila gateway tertentu (Fonnte, Wablas, dll.) dipilih.
- Pengingat jadwal jaga sekarang tercatat sekali per petugas per tanggal (channel `all`), apa pun kanalnya.

### Akses
- Monitor: lihat `monitors.view` (kepala unit, petugas IT); kelola & cek sekarang `monitors.manage` (petugas IT).
- Insiden: hanya catatan yang bisa diubah; dibuat/ditutup oleh mesin.
- Pengaturan monitoring: `monitoring.settings` (super admin).

### Lingkungan lokal
- `composer run dev` menjalankan `queue:listen --queue=monitoring,default` dan `schedule:work`.
  Monitor contoh memakai IP dokumentasi/URL example.ac.id, jadi bila scheduler jalan semuanya akan menjadi
  Offline dalam beberapa menit — wajar untuk lokal.

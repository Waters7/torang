# Keputusan Teknis TORANG

Catatan keputusan yang tidak diatur langsung oleh `docs/SPEC.md`. Tambahkan entri baru di bawah
fase terkait.

## Riwayat penanganan tiket & aplikasi kampus (5 November 2026)

- Atas permintaan pengguna, rencana/tindakan/perkembangan menjadi entri tersendiri yang hanya ditambahkan,
  tanpa tombol ubah/hapus. Penulis berasal dari pengguna terautentikasi; status tiket disalin saat pencatatan
  di dalam transaksi dengan kunci baris. Respons pertama diisi sekali bila sebelumnya kosong.
- Catatan baru secara default publik bagi pemilik nomor tiket, dengan pilihan **Hanya untuk tim IT** sebelum
  disimpan. Form menjelaskan audiens ini. Komentar lama tetap memakai aturan tautan privat sebelumnya;
  tidak ada perubahan massal terhadap visibilitas catatan lama.
- Resource publik memilih isi catatan publik, jenis, penulis, waktu, status saat dicatat, serta nama petugas
  yang ditugaskan. Identitas/kontak pelapor, token, dan catatan internal tidak dikirim. Halaman cek status memakai
  `no-store` dan `no-referrer`; polling 30 detik memperbarui riwayat. Pencatatan tidak mengirim notifikasi eksternal.
- Daftar 13 URL asli disimpan di `config/campus-applications.php` dan diterapkan lewat perintah eksplisit
  `monitors:sync-campus-applications`; bukan seeder otomatis. Pencocokan host/path/query menoleransi perbedaan
  HTTP/HTTPS dan garis miring penutup. Nama, kategori, dan URL mengikuti daftar; privasi, jeda, maintenance,
  PIC, interval, dan riwayat monitor yang sudah ada dipertahankan.
- Monitor baru aktif/publik dengan status **Belum diperiksa**, HTTP check, dan pengaturan interval/ambang global.
  Sebelas URL HTTPS mengaktifkan pemeriksaan SSL; P360 dan TORANG memakai HTTP sesuai URL pengguna.
  Tidak dibuat riwayat atau uptime sintetis. Opsi `--pause-samples` hanya menjeda pasangan nama/URL persis dari
  tujuh aplikasi contoh, menyembunyikannya dari publik tanpa menghapus data.
- Sinkronisasi dilindungi kunci cache dan transaksi, lalu menghapus cache status publik. Pemeriksaan awal lokal
  dilakukan sekali setelah pendaftaran; hasil gagal pertama mengikuti ambang tiga kegagalan sebelum Offline.
  Pemeriksaan berkala tetap membutuhkan scheduler serta worker queue `monitoring,default` yang berjalan.

## TV IT: bagian lipat dan tiket belum selesai (2 November 2026)

- Bagian jaringan/aplikasi memakai elemen native `details`/`summary`, terbuka secara default, dengan
  `wire:ignore.self` dan kunci stabil. Livewire memperbarui isi tanpa mengubah atribut buka/tutup milik browser.
  Pilihan berlaku selama layar terbuka; muat ulang penuh kembali ke kondisi awal. Monitoring tetap berjalan.
- Antrean tiket memakai `TicketStatus::unfinished()` dan `TicketPolicy::viewAny`, bukan batas tanggal hari ini.
  Tiket diurutkan Mendesak → Tinggi → Normal → Rendah, lalu waktu masuk terlama dan ID. Seluruh antrean tersedia
  dalam area gulir berfokus keyboard, tanpa pemotongan jumlah tiket.
- `TvBoard` hanya memilih kolom yang diperlukan serta memuat relasi petugas/unit sekaligus; data TV tidak memuat
  nama/kontak pelapor, deskripsi, token, atau catatan internal. Tiket tidak di-cache dan mengikuti polling 60 detik.

## Keseragaman visual TV IT dan Lab CBT (2 November 2026)

- Tema Filament memakai Tailwind `source(none)`, sehingga folder `resources/views/components` harus
  didaftarkan sebagai sumber kelas. Komponen tab bersama kini ikut build tema admin, termasuk latar aktif,
  jarak antar-tab, tinggi tombol, dan outline fokus.
- Header IT mengikuti ukuran serta jarak layar lab, memakai aset logo dan supergrafis Poltekkes yang sama.
  Tombol layar penuh dan Dashboard berada di footer agar posisi header dan baris tab konsisten.
- Verifikasi visual IT memakai render template sebenarnya dengan data contoh pada server pratinjau lokal
  sementara; akses halaman asli tetap diuji melalui feature test tanpa mengubah akun atau izin lokal.

## Tab Mode TV IT / Lab CBT (2 November 2026)

- `TvNavigation` menjadi sumber daftar tab bersama untuk tampilan Filament IT/NOC dan React TV lab.
  Nama/URL lab disaring melalui `TvLabResource`, hanya lab aktif atau perawatan, mengikuti urutan kode Master Lab.
- Tab berupa navigasi tautan dengan `aria-current="page"`, bukan panel yang dimuat sekaligus; perpindahan memakai
  navigasi HTML karena layar IT dan lab memakai dua renderer berbeda. Setiap layar tetap punya URL langsung.
- Polling menyegarkan daftar pilihan bersama konten layar. Pada ponsel daftar dapat digeser horizontal;
  tab lab aktif otomatis terlihat setelah navigasi, tanpa menggeser halaman secara vertikal.
- Akses mengikuti implementasi yang sudah ada: IT/NOC `/admin/tv` memerlukan petugas aktif dengan izin
  `monitors.view`, sementara `/tv/lab/{kode}` tetap publik tanpa data pribadi pemohon.

## Akses Mode TV dari beranda (29 Oktober 2026)

- Permintaan menaruh Mode TV / NOC di halaman depan diterapkan sebagai kartu akses ke `/admin/tv`.
  Menggunakan navigasi HTML biasa karena tujuannya halaman Filament, bukan respons Inertia.
  Pengunjung diarahkan ke login jika belum masuk; permission `monitors.view` tetap diperlukan.

## Penyempurnaan UI & uji LAN (28 Oktober 2026)

- Permintaan pengguna memperbarui susunan beranda SPEC §9.2: banner bergeser, kartu layanan,
  ringkasan, kondisi lab, jadwal lab, nama petugas minggu berjalan, jaringan, dan kartu aplikasi.
  Pengumuman tetap tersedia di luar slider; insiden terbuka sejak awal. Slider otomatis setiap
  delapan detik, berhenti ketika disorot/fokus, dengan kontrol jeda, panah, dan keyboard.
- Jadwal publik berasal dari minggu Senin berjalan yang berstatus Published, Senin–Sabtu mengikuti
  modul petugas (bukan pengaturan Minggu opsional untuk booking). Penugasan di luar rentang disaring,
  nama dikelompokkan berdasarkan shift dan cakupan; tidak menerbitkan draf atau mengisi jadwal otomatis.
  Resource API melakukan whitelist; cache hanya data tersaring selama 30 detik, dengan kunci per minggu.
- Polling beranda mencakup jadwal lab dan petugas, sehingga penerbitan/pergantian minggu ikut terlihat.
  Waktu pemeriksaan monitor ditampilkan lengkap dengan tanggal; data lama tidak diberi kesan baru diperiksa.
- Neumorphism memakai token CSS bersama, tanpa pustaka animasi tambahan. Tema publik dibatasi pada
  PublicLayout; tema Filament resmi dimuat melalui viteTheme. Animasi tambahan memakai opacity/transform,
  mendukung prefers-reduced-motion, dan bayangan dihilangkan pada cetakan. Warna status dan outline fokus
  tetap membedakan keadaan/kontrol. Mode gelap dashboard mempertahankan preferensi pengguna/perangkat.
- ResizeObserver pada wadah Turnstile mengganti ukuran normal/compact saat melewati 300 px. Token
  sebelumnya dikosongkan ketika widget dibuat ulang; validasi captcha di server tetap berlaku.
- Pengujian LAN memakai proses PHP terpisah yang hanya bind ke IPv4 adapter terpilih, sehingga server
  loopback yang sudah berjalan tetap tersedia. Firewall Windows mengizinkan executable PHP dan TCP 8001
  hanya pada IP/adapter tersebut, dari prefix subnet yang dihitung dari konfigurasi adapter.
  Profil jaringan dan firewall global tidak diubah; aturan mencakup profil aktif termasuk Wi-Fi Public.
  Tidak ada port forwarding, pembukaan MySQL, maupun auto-start layanan setelah reboot.
- APP_URL LAN dan APP_DEBUG=false hanya diterapkan pada konfigurasi lokal; rahasia .env tidak masuk Git.
  Ini sarana UAT di LAN, bukan penyelesaian deployment VPS Fase 8.

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

## Fase 4 — Beranda & Status Publik (24 Oktober 2026)

### Paket
- Kalender publik memakai FullCalendar **v7** dalam satu paket `@fullcalendar/react` dengan impor subpath
  (`/daygrid`, `/list`, `/themes/classic`, `/locales/id`) + `temporal-polyfill`, sesuai README v7.
  Paket v6 (`@fullcalendar/core`, `daygrid`, `list`) tidak dipakai karena bentrok dengan v7.

### Data
- Satu service `PublicStatus` menyusun semua data publik; tiap bagian di-cache 30 detik (`public.*`)
  sehingga polling banyak klien tidak membebani database.
- Kartu lab: "PC dipakai" = jumlah peserta kegiatan berlangsung / PC siap pakai lab. Lab nonaktif tidak tampil.
  Maintenance bila status lab Maintenance atau kegiatan berlangsung bertipe maintenance.
- Jadwal minggu dan kalender hanya memuat pengajuan Disetujui/Selesai; Minggu ikut tampil bila `bookings.show_sunday`.
- Bar 24 jam: status terburuk per jam dari `monitor_checks` (abu-abu bila tanpa data).
  Bar 30/90 hari: dari `monitor_daily_stats`, berakhir kemarin (hari ini belum diagregasi);
  hijau ≥ `public.uptime_good_percent`, kuning ≥ `public.uptime_warning_percent`, merah di bawahnya.
- Halaman `/status` menampilkan insiden 30 hari terakhir plus insiden yang masih berlangsung (maks. 50).
- Hanya monitor `is_public` + aktif yang tampil; nama jaringan memakai nama jurusan, bukan host.

### Akses & beban
- `/api/public/summary` dan `/api/public/events` `throttle:60,1`; `/api/public/events` menolak rentang > 62 hari.
- Halaman Inertia `/`, `/status`, `/jadwal` tidak diberi rate limit per IP: pengunjung kampus umumnya
  berbagi satu IP NAT sehingga limit per IP berisiko memblokir banyak orang sekaligus; beban ditahan cache 30 detik.

### Tampilan
- Auto-refresh memakai `usePoll` dengan partial reload (`only`), interval dari `public.refresh_seconds` (min. 15 detik).
- Di ponsel: tabel jadwal minggu digeser horizontal di dalam kontainernya, kalender berganti ke tampilan daftar,
  pill bertulisan panjang boleh turun baris (`wrap`), celah bar riwayat 1 px agar 90 bar muat.
- Widget Turnstile memakai ukuran `compact` bila lebar kontainernya < 300 px.
- Banner pengumuman di beranda (SPEC §9.2) dikerjakan bersama modul Pengumuman di Fase 7.

## Fase 5 — WiFi & Pengingat Hosting (24 Oktober 2026)

### WiFi
- Konfirmasi password memakai penanda session bawaan Laravel (`auth.password_confirmed_at`, sama dengan
  middleware `password.confirm`) dan `auth.password_timeout` yang diubah default-nya menjadi 300 detik (5 menit).
  Konfirmasi dilakukan di modal Filament, bukan halaman terpisah; salah password dibatasi 5×/menit per pengguna.
- Alur aksi: "Lihat password"/"Kartu QR" meminta password akun bila belum terkonfirmasi, lalu diganti aksi
  `showWifiPassword` (hanya bisa dibuka bila sudah terkonfirmasi & punya izin). Isi password baru dikirim ke browser
  saat modal itu dibuka.
- "Salin" menyalin di browser lalu memanggil server untuk dicatat (penyalinan clipboard tidak bisa diverifikasi server).
- Activity log WiFi (`log_name` = wifi) memakai event `password_revealed`, `password_copied`, `qr_printed`,
  `password_changed`; perubahan data biasa tercatat tanpa kolom password. Semua dengan IP & user agent.
- Jadwal ganti: password_changed_at + siklus per SSID (atau `wifi.rotation_days`); "Segera ganti" bila ≤ 7 hari.
  Jaringan Open tidak menyimpan password; mengubah ke Open memindahkan password lama ke riwayat.
- Data WiFi tidak diekspor (berisi password). Kartu QR memuat password (dicetak untuk ditempel), jadi aksi ini
  butuh izin lihat password + konfirmasi dan tercatat.
- `chillerlan/php-qrcode` (sudah terpasang sebagai dependensi Filament) dijadikan dependensi langsung; QR dibuat
  PNG (butuh ekstensi gd, sudah ada di daftar ekstensi server SPEC §15).

### Hosting
- Status turunan dihitung dari `status` + `expires_at` (tidak disimpan); "Segera berakhir" = sisa 0–30 hari.
- Estimasi biaya per tahun = biaya per siklus × 12 ÷ bulan siklus, hanya layanan aktif. Siklus tersedia:
  bulanan, 3 bulan, 6 bulan, tahunan, 2 tahun, 3 tahun.
- Catat pembayaran: periode default mulai dari tanggal berakhir saat ini (atau hari ini bila sudah lewat > 1 bulan),
  selesai = mulai + 1 siklus. `expires_at`/`last_paid_at` hanya maju, tidak mundur, saat mencatat pembayaran lama.
  Menghapus pembayaran tidak mengubah tanggal layanan (koreksi lewat form layanan).
- Bukti bayar hanya PDF ≤ 2 MB (aturan upload CLAUDE.md), disk privat `hosting/payments`, unduh lewat
  `/admin/unduh/bukti-bayar/{id}` dengan izin `hosting.view`.
- Provider yang masih punya layanan, dan layanan yang sudah punya pembayaran, tidak bisa dihapus.
- Pengingat: kunci `expiry:{tanggal berakhir}:{ambang}`; ambang terlewat dikirim sekali; layanan yang sudah
  kedaluwarsa tapi masih Aktif diingatkan sekali (ambang 0); layanan Dihentikan tidak diingatkan; layanan dengan
  perpanjangan otomatis tetap diingatkan dengan keterangan. Email akun hosting tidak dimuat di pesan.
- Penerima email: PIC layanan; bila kosong, pengguna aktif ber-izin `hosting.manage`. PIC juga menerima
  Telegram/WhatsApp pribadi bila kanal aktif dan `telegram_chat_id`/`phone` diisi di akunnya
  (`User` kini punya rute notifikasi Telegram & WhatsApp).
- Ekspor Excel: 3 sheet (Layanan termasuk yang dihentikan, Anggaran per provider, Pembayaran tahun terpilih),
  tanggal sebagai tanggal Excel dan nominal angka berformat Rupiah.

### Lingkungan lokal / test
- Unggahan sementara Livewire dalam test dibersihkan bila waktu dimajukan (`travelTo`), jadi test unggah bukti
  bayar memakai tanggal nyata.
- Pengaturan siklus WiFi & hari pengingat hosting masih memakai nilai default; halaman Pengaturan di Fase 7.

## Fase 6 — Laporan UKOM (24 Oktober 2026)

### Data & nomor surat
- Nomor urut dipesan saat draf pertama kali disimpan (agar nomor tampil di dokumen draf), dihitung dari nomor
  terbesar tahun surat termasuk laporan yang diarsipkan, dengan `lockForUpdate` + indeks unik (letter_year,
  letter_sequence) dan satu kali coba ulang bila bentrok. Draf yang diarsipkan tetap memegang nomornya.
- Nomor yang diketik manual tetap dicek unik; bila mengikuti pola (mis. `.../023/2026`), nomor urutnya dicatat
  sehingga nomor otomatis berikutnya melanjutkan (024). Token pola: `{seq}`/`{seq:N}`, `{year}`, `{month}`, `{roman}`.
- Tahun nomor = tahun tanggal surat; mengganti tahun pada draf memesan nomor baru di tahun itu.
- "Arsipkan" = soft delete; laporan tidak pernah dihapus permanen.
- Kolegium & penanda tangan disalin ke laporan (snapshot). Kolegium yang sudah dipakai tidak bisa dihapus.
- Kolegium TPD di-seed di semua lingkungan (data master, seperti provider hosting); laporan contoh hanya non-produksi.

### Wizard
- Teks turunan (nama kegiatan, hal, Kepada Yth., teks baris rincian) diperbarui otomatis selama isinya masih sama
  dengan hasil otomatis sebelumnya; begitu diedit manual tidak ditimpa lagi.
- Teks rincian otomatis mengikuti dokumen asli: tanpa volume 2 → `Uraian 2 unit (Rp 40.000/unit)`; dengan volume 2 →
  `Uraian 2 orang untuk 2 hari (ket) Rp 50.000,00/org` (satuan "orang" disingkat "org").
- Langkah 5 berisi ringkasan; pratinjau PDF ada di halaman detail setelah disimpan (PDF perlu laporan tersimpan
  dan diproses queue).
- Penanda tangan bisa diambil dari data petugas (nama + NIP dari data petugas/DUK); NIP wajib 18 digit.

### Dokumen
- `UkomReportDocumentService` mengikuti contoh referensi (escape aktif, clone blok `rincian`, cek `getVariables()`).
  Kegagalan membuat DOCX tidak membatalkan penyimpanan draf; pesannya tampil di halaman detail.
- DOCX dibuat ulang setiap simpan/finalkan; PDF lama dibuang lalu konversi baru diantrekan.
- LibreOffice: perintah `LIBREOFFICE_BINARY` (default `soffice`) lewat Process facade dengan argumen array,
  timeout 60 detik, HOME = `storage/app/libreoffice`. Bila perintah tidak ditemukan, tombol PDF nonaktif.
- Template versi 1 dibuat otomatis dari `resources/templates/` ke `storage/app/private/templates/ukom_report/`.
  Template baru wajib memuat tepat placeholder SPEC §8A.5 (kurang atau ada yang tidak dikenal → ditolak).
- Ganti template hanya Super Admin (izin `settings.manage`); pengaturan penanda tangan & pola nomor untuk pemegang
  izin Laporan UKOM. Halaman Pengaturan UKOM ada di grup Lab CBT (bukan menunggu halaman Pengaturan Fase 7).
- Format: `Rupiah::format()` = `Rp 280.000,00`; `Terbilang::id()` sampai triliun; tanggal surat `j F Y`,
  tanggal kegiatan `d F Y`.

### Lokal
- Muatan pertama halaman Filament setelah perubahan bisa > 30 detik di `artisan serve` drive X: (kompilasi view);
  muat ulang setelah selesai.

## Fase 7 — Helpdesk, Pengumuman, Laporan & Dashboard (27–28 Oktober 2026)

### Helpdesk dan pengumuman
- Tiket dimulai pada status Baru dengan prioritas Normal. Prioritas diubah petugas setelah laporan diterima.
  Kode `TIK-YYMM-` diikuti 12 digit heksadesimal acak; indeks unik di database.
- Laporan dari log pemakaian memakai izin memperbarui log tersebut, mengunci baris saat pembuatan,
  dan mempunyai indeks unik `usage_log_id` agar satu kendala tidak menghasilkan tiket ganda.
- Dikerjakan wajib mempunyai petugas aktif. Selesai dapat dibuka kembali ke Dikerjakan;
  Ditutup hanya dari Selesai dan tidak dibuka kembali. Waktu penyelesaian dikosongkan saat dibuka kembali.
- `/cek-tiket` hanya menampilkan kode, kategori, status, dan waktu. Nama/kontak pelapor, uraian,
  lampiran, serta komentar internal tidak dikirim ke endpoint publik tersebut.
- Tautan pribadi menggunakan token acak 64 karakter; database hanya menyimpan hash SHA-256.
  Tautan ditampilkan sekali setelah pembuatan tiket (publik maupun internal) untuk disimpan/disampaikan
  kepada pelapor. Tanggapan non-internal dan penilaian hanya tersedia melalui tautan tersebut.
  Penilaian 1–5 hanya sekali pada status Selesai/Ditutup, dengan Turnstile dan honeypot.
- Halaman bertoken memakai `no-store` dan `no-referrer`. PDF lampiran maksimal 2 MB disimpan
  privat dan hanya dapat diunduh pemegang izin melihat tiket. Activity log memakai daftar kolom aman;
  token, isi komentar, dan kontak pelapor tidak dicatat dalam perubahan tiket.
- Pengumuman berupa teks biasa yang di-escape saat ditampilkan, tanpa HTML bebas. Banner aktif bila
  `is_public`, waktu mulai sudah lewat, dan waktu akhir belum lewat/tidak diisi; ikut polling beranda.
  Tipe Maintenance adalah pemberitahuan dan tidak otomatis menjeda monitor.
- FAQ opsional belum ditambahkan; notifikasi keluar khusus helpdesk tidak ditambahkan di luar cakupan SPEC.

### Laporan operasional
- Periode bebas maksimal 366 hari mencakup kebutuhan bulanan/semester. Filter lab dan penyelenggara
  untuk CBT; filter jurusan untuk SLA. Excel/PDF selalu dihitung kembali dari filter tervalidasi.
- CBT menghitung pengajuan Disetujui/Selesai yang jam akhirnya sudah lewat; Maintenance dikecualikan.
  Peserta terdaftar dipisahkan dari kehadiran aktual; bila belum ada log, kehadiran ditulis Tidak ada data.
  Pengajuan yang belum berakhir ditampilkan terpisah sebagai Masih terjadwal.
- Utilisasi memakai menit kegiatan dalam jam/hari operasional sampai sekarang dibagi menit operasional
  tersedia sampai sekarang. Hari operasional default Senin–Sabtu dan dapat diatur. Pengaturan yang berlaku
  adalah saat laporan dibuat, belum ada kalender hari libur maupun riwayat perubahan jam operasional.
  Filter penyelenggara memperlihatkan kontribusinya terhadap keseluruhan kapasitas lab.
- SLA memakai agregasi harian permanen: `(Online + Lambat) / jumlah cek`, tertimbang berdasarkan cek,
  bukan rata-rata persentase harian. Tanpa cek ditulis Tidak ada data; hari berjalan masuk setelah agregasi.
  Durasi insiden yang bersinggungan dipotong pada batas periode dan waktu saat ini.
- Excel mempertahankan nilai angka dan menetralkan awalan formula pada teks dari master data.
  PDF A4 lanskap dibuat langsung lewat DomPDF; tidak membutuhkan LibreOffice.

### Dashboard dan sistem
- Widget hanya memuat kelompok data sesuai permission pengguna aktif. Ringkasan monitor menyertakan
  jumlah status dan tanggal/jam cek terakhir; grafik uptime meliputi tujuh hari selesai, celah berarti tanpa data.
- Mode TV `/admin/tv` tetap memerlukan login dan `monitors.view`, menyaring data melalui layanan dashboard
  yang sama, dengan layar penuh dan pembaruan 60 detik. Tidak ada akses TV anonim/token pada fase ini.
- Pengaturan umum melengkapi identitas, booking, pengingat, WiFi, dan tampilan publik. Monitoring/notifikasi
  dan UKOM memakai halaman pengaturan yang sudah ada; rahasia tetap di `.env`.
- Empat role bawaan tetap digunakan, permission-nya dapat diatur; Super Admin selalu lengkap. Seeder
  hanya mengisi default untuk role baru dan menjaga Super Admin, tanpa menimpa penyesuaian role lain.
- Pengelolaan akun memerlukan `users.manage` dan `roles.manage` karena mencakup pemberian role.
  Password edit kosong mempertahankan password lama, tidak memuat hash ke form. Pengguna tidak bisa
  menonaktifkan dirinya sendiri atau mencabut Super Admin dirinya; admin aktif terakhir dilindungi.
- Audit log hanya baca: waktu, pelaku, modul, deskripsi, referensi objek, dan IP jika tersedia;
  properti mentah tidak ditampilkan.

### Backup
- Backup berisi database saja, disimpan pada disk privat `backups` di `storage/app/private/backups`.
  Lampiran dan berkas dokumen berada di luar cakupan arsip database ini. Unduhan memerlukan
  `backups.manage`, nama file tervalidasi, dan pencocokan dengan daftar arsip yang tersedia.
- Job unik di queue `default` pukul 02.00 WITA; cleanup pukul 02.30 WITA. Retensi 14 hari memakai
  strategi bawaan Spatie, yang tetap mempertahankan backup terbaru bila belum ada penggantinya.
  Hasil terakhir dicatat sebagai Berjalan/Berhasil/Gagal. Notifikasi eksternal paket dinonaktifkan.
- Program dump dapat ditentukan melalui `DB_DUMP_BINARY_PATH`; path Laragon tidak di-hardcode.
  `BACKUP_ARCHIVE_PASSWORD` opsional untuk enkripsi ZIP. Verifikasi integritas arsip diaktifkan.
- Verifikasi lokal menjalankan job dengan MySQL sungguhan, membuka ZIP, dan memeriksa dump SQL
  tidak kosong. Akun QA sementara dihapus sebelum dump; tidak ada reset/restore database pengguna.

## Tambahan — Tampilan halaman depan, WiFi publik & maintenance (30 Oktober 2026)

### Pengecualian aturan WiFi (keputusan pengguna)
- CLAUDE.md mewajibkan password WiFi tidak dikirim ke halaman publik dan halaman publik tidak menampilkan SSID.
  Atas permintaan pengguna (30 Oktober 2026, pilihan "Semua SSID yang di-ON"), SSID yang dipilih petugas
  boleh tampil **beserta password** di beranda. CLAUDE.md tidak diubah; pengecualian ini hanya berlaku untuk
  SSID yang dipilih dan saat sakelar WiFi menyala.
- Pengaman: default OFF (sakelar global `public.show_wifi` dan `is_public` per SSID); menampilkan butuh izin
  `wifi.manage` + konfirmasi; setiap tampil/sembunyi tercatat (event `published`/`unpublished`, IP & user agent);
  data WiFi publik tidak di-cache dan hanya untuk SSID aktif; tidak ikut di /status maupun API ringkasan.
- Di dashboard, password SSID terpilih terlihat langsung (sudah publik); SSID lain tetap lewat "Lihat password"
  dengan konfirmasi akun (SPEC §6).

### Sakelar tampilan
- `public.show_networks`, `public.show_apps` (default ON, izin `monitors.manage`) dan `public.show_wifi`
  (default OFF, izin `wifi.manage`), diatur dari widget dashboard. Grup yang disembunyikan tidak dikirim ke
  halaman publik sama sekali (beranda, /status, insiden, API ringkasan); ringkasan grupnya bernilai 0.
- Pilihan tampil per monitor memakai kolom `is_public` yang sudah ada. Perubahan mengosongkan cache publik.
- `usePoll` beranda ikut memuat `display` dan `wifi` agar sakelar berlaku tanpa memuat ulang halaman.

### Maintenance per monitor
- Status baru `maintenance` (label "Maintenance", warna kuning seperti Lambat) + kolom `maintenance_note`,
  `maintenance_started_at`, `maintenance_until`.
- Selama maintenance: monitor tidak dikirim ke queue, hasil cek yang terlambat diabaikan (tidak disimpan, tidak
  memengaruhi uptime/SLA), insiden terbuka ditutup dengan catatan tanpa notifikasi pulih, "Cek sekarang" disembunyikan.
- Berakhir manual atau otomatis di awal `monitors:dispatch` saat perkiraan selesai lewat; setelahnya status
  "Tidak diketahui" dan langsung dicek. Menonaktifkan monitor juga mengakhiri maintenance.
- Izin `monitors.manage` (Petugas IT, Super Admin); Kepala Unit hanya melihat.

## Fase 8 — Finalisasi & Deployment VPS (30 Oktober 2026)

### Keamanan
- Middleware `SecurityHeaders` (grup web + panel Filament) menambah `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`
  (kamera, mikrofon, lokasi, dll. dimatikan), dan `Cross-Origin-Opener-Policy: same-origin`, hanya bila
  respons belum mengaturnya (mis. `no-referrer` pada halaman kode tiket tetap dipakai). HSTS hanya dikirim
  untuk request HTTPS di produksi agar pengujian lokal/LAN lewat HTTP tidak terkunci.
- **Tanpa Content-Security-Policy** untuk saat ini: Filament/Livewire/Alpine memakai skrip & style inline dan
  Turnstile memuat skrip pihak ketiga, sehingga CSP yang ketat butuh nonce di banyak tempat dan berisiko
  merusak panel. Bisa ditambahkan kemudian dengan mode report-only lebih dulu.
- Proxy tepercaya lewat env `TRUSTED_PROXIES` (daftar IP dipisah koma atau `*`), kosong secara default.
  `URL::forceHttps()` hanya di produksi bila `APP_URL` berawalan `https://`.
- Login panel sudah dibatasi Filament (5 percobaan per menit); ditambah test.

### Permission setelah deploy
- `torang:sync-permissions` membuat permission yang belum ada dan **hanya** memberikan permission yang baru dibuat
  ke role bawaan yang memilikinya di `Role::permissions()`. Permission yang sengaja dicabut di panel tidak
  dikembalikan. Super Admin, role yang baru dibuat, atau opsi `--reset` disinkronkan persis seperti bawaan.
  Dijalankan otomatis oleh `deploy.sh`.

### Performa
- `Model::preventLazyLoading()` aktif di luar produksi supaya N+1 baru langsung terlihat saat pengembangan/test;
  di produksi tidak melempar error.
- `SettingsService` di-bind `scoped` dan menyimpan hasil cache di memori selama satu request/job; `flush()`
  mengosongkan keduanya.
- Index tambahan: `activity_log.created_at` (filter & urutan Audit Log) dan
  `monitor_incidents (monitor_id, resolved_at)` (insiden terbuka per monitor).
- `retry_after` queue database/redis default 360 detik, lebih lama dari job terlama (backup 300 detik); timeout
  worker di contoh Supervisor (monitoring 120, default 330) dibuat lebih kecil dari angka itu.

### Kesiapan server
- `torang:check` hanya membaca keadaan: APP_KEY, APP_DEBUG, HTTPS & cookie aman, koneksi database, migrasi
  tertunda, super admin aktif, folder storage, ping ke 127.0.0.1 (Process argumen array), LibreOffice,
  `mysqldump`/`mariadb-dump`, detak scheduler, job tertahan > 5 menit, job gagal, captcha, mailer, Telegram.
  Status OK/PERINGATAN/GAGAL; yang wajib di produksi (APP_DEBUG mati, captcha terisi, database, migrasi, super
  admin, storage) menjadi GAGAL dan membuat kode keluar non-nol. Kelompok yang error dicatat GAGAL tanpa
  menghentikan pemeriksaan lain.
- Detak scheduler: tugas `torang:scheduler-heartbeat` tiap menit menulis waktu ke cache; dianggap hidup bila ≤ 3 menit.

### Deployment
- `deploy.sh`: mode maintenance → `git pull --ff-only` → `composer install --no-dev -o` → `npm ci && npm run build`
  → `migrate --force` → `torang:sync-permissions` → `optimize` + `filament:optimize` → `queue:restart` → `up` →
  `torang:check`. Isi skrip dibungkus fungsi agar perubahan skrip saat `git pull` tidak memengaruhi eksekusi
  yang sedang berjalan; bila gagal, aplikasi tetap dalam mode maintenance.
- Kode dimiliki user deploy (`torang`); PHP-FPM, worker, dan cron berjalan sebagai `www-data` dengan hak tulis
  hanya pada `storage/` dan `bootstrap/cache/` lewat ACL. Semua unggahan memakai disk privat, jadi
  `storage:link` tidak diperlukan.
- Contoh Nginx ditulis untuk Nginx 1.24 bawaan Ubuntu 24.04 (`listen 443 ssl http2`), tanpa bergantung pada file
  tambahan Certbot; sertifikat diambil dengan `certbot certonly --nginx`. Aset `/build` di-cache 1 tahun
  (nama ber-hash); file PHP selain `index.php` dan dotfile ditolak.
- Supervisor: 3 worker `monitoring` (rentang SPEC 2–4) dan 1 worker `default`, restart tiap jam (`--max-time`).
- Ping sebagai `www-data` memakai `net.ipv4.ping_group_range` (contoh di `deploy/sysctl`) bila distribusi belum
  mengizinkannya; alternatif `setcap cap_net_raw`.
- `composer.json` mengunci platform PHP 8.3.0, sehingga `composer.lock` cocok dengan PHP 8.3 bawaan Ubuntu 24.04.
- Node.js 22 LTS (minimal 22.12 untuk Vite 8), sama dengan lingkungan pengembangan.

### Perapian Fase 7
- Enum `TicketStatus`, `TicketPriority`, `TicketCategory`, `AnnouncementType` memakai trait label/warna yang sama
  dengan enum lain; `TicketStatus::unfinished()` dipakai dashboard & laporan.
- Aksi tiket (tindak lanjut, komentar, unduh lampiran, buat dari log pemakaian) dipindah ke `TicketActions`;
  aturan validasi tiket publik dipakai bersama `TicketService::rules()` dan `StoreTicketRequest`.
- `OperationsDashboard` dan `OperationalReports` dipecah per bagian tanpa mengubah hasil; seluruh test lama tetap lulus.

## Tambahan — Dashboard bertab, Dashboard Eksekutif, TV per lab (31 Oktober 2026)

### Dashboard bertab
- `App\Filament\Pages\Dashboard` menggantikan dashboard bawaan Filament. Tab didefinisikan di satu tempat
  (label, ikon, warna aksen, izin, widget); tab tampil bila pengguna punya salah satu izinnya. Hanya widget tab aktif
  yang dimuat (`?tab=`), dan kunci Livewire memuat nama tab agar widget yang sama di tab lain dimuat ulang.
- `OperationsOverview` menerima `section` (attention/cbt/monitoring/infrastructure/helpdesk); tanpa section tetap
  mengembalikan semua kelompok seperti sebelumnya. `AccountWidget` tidak lagi dipasang (menu pengguna sudah ada).
- "Perlu tindakan" = pengajuan menunggu (3 terdekat), monitor Offline/Maintenance, hosting jatuh tempo ≤ 7 hari
  atau lewat, dan jumlah tiket tanpa petugas.

### Palet Kemenkes
- Empat warna dari Pedoman Identitas Kemenkes (turunan logo UPT Poltekkes): teal `#04B6A3` (C75 Y45),
  lime `#D4E022` (C20 Y100), cyan `#1BBBCB` (C74 Y21), hijau `#42B97C` (C69 Y68), disimpan di
  `OperationsDashboard::ACCENTS` dan variabel CSS `--kemenkes-*`. Lime hanya untuk aksen/garis/latar ikon, tidak untuk
  teks di latar terang. Warna primer panel tetap `#08796C` agar kontras teks aman.
- Garis empat warna memakai `linear-gradient` dengan batas keras (blok warna padat, bukan gradien visual).

### Dashboard Eksekutif
- Halaman Filament dashboard kedua (`/admin/eksekutif`) dengan filter periode: Bulan ini, 3 bulan terakhir (bawaan),
  6 bulan terakhir, Tahun ini. Periode pembanding = jumlah hari yang sama tepat sebelum periode. Grafik per minggu
  untuk "Bulan ini", selain itu per bulan.
- Indikator: utilisasi lab (rata-rata per lab; menit terpakai ÷ menit operasional sampai saat ini, memakai helper
  laporan CBT), kegiatan CBT & peserta (tanpa maintenance), uptime jaringan tertimbang dari statistik harian,
  pengaduan jaringan (tiket kategori jaringan), median waktu tanggap & penyelesaian tiket, rata-rata penilaian pelapor.
- Ambang insight (konstanta `ExecutiveInsights`): lab padat ≥ 80%, jarang dipakai ≤ 20%, PC rusak ≥ 10% (≥ 25%
  kritis), uptime jaringan < 99,5% (< 95% kritis, sama dengan ambang bar status), pengaduan naik ≥ 30% (minimal 3
  tiket), median waktu tanggap > 4 jam, tiket belum selesai > 3 hari, pengajuan menunggu > 2 hari, hosting kedaluwarsa
  atau ≤ 30 hari (dengan perkiraan biaya). Bila tidak ada temuan Perlu perhatian/Kritis, tampil "Semua indikator dalam
  batas normal".
- Izin baru `dashboard.executive` (Kepala Unit; Super Admin otomatis semua izin). Widget juga memeriksa izin ini.

### Waktu tanggap tiket
- Kolom `tickets.first_responded_at`: diisi saat tindak lanjut pertama petugas yang mengubah status, prioritas, atau
  petugas, atau saat komentar petugas pertama. Simpan tanpa perubahan tidak dihitung.
- Tiket lama diisi migrasi dari komentar petugas pertama atau waktu selesai (yang lebih awal); bila sudah bukan
  "Terbuka" tanpa keduanya, waktu perubahan terakhir dipakai sebagai perkiraan. Nilai tidak pernah sebelum tiket diterima,
  dan durasi dibatasi minimal 0.

### Layar TV per lab
- Route publik `/tv/lab/{kode}` (tanpa login, `throttle:60,1`), React + Inertia, `usePoll` 30 detik, jam berjalan di
  klien (WITA). Datanya sama dengan kartu "Sedang berlangsung" di beranda: nama kegiatan, penyelenggara singkat, jam,
  jumlah peserta, PC siap, petugas jaga — tanpa nama/NIP/email/WhatsApp pemohon dan tanpa kode pengajuan
  (`LabTvSessionResource`). Lab nonaktif → 404. Tidak di-cache karena query ringan dan per lab.
- Isi: sedang berlangsung (progres & sisa menit), sesi berikutnya hari ini, sudah selesai hari ini (maks. 4 baris
  per daftar), dan sesi pertama dalam 7 hari ke depan bila hari ini tidak ada sesi lagi.

### Versi & favicon
- `config('torang.version')` (1.1.0) tampil di halaman login dan menjadi penanda `?v=` pada tautan favicon publik &
  panel (partial `partials/favicons`), sehingga browser mengganti ikon yang tersimpan di cache.

## Tambahan — Lupa kata sandi & captcha masuk petugas (31 Oktober 2026)

### Lupa kata sandi
- Memakai alur reset bawaan Filament/Laravel (`->passwordReset()`), dengan halaman sendiri di `app/Filament/Auth`
  (bukan `app/Filament/Pages`, agar tidak ikut auto-discovery sebagai halaman panel).
- Tautan hanya dikirim ke akun yang `canAccessPanel` (aktif dan punya role). Pesan ke peminta selalu
  "Periksa email Anda…" — tidak membedakan email tidak dikenal, nonaktif, atau dibatasi — untuk mencegah enumerasi
  akun (bawaan Filament menampilkan "email tidak ditemukan").
- Email `StaffPasswordReset` berbahasa Indonesia, lewat antrean `default` (sama dengan notifikasi lain), tautan
  bertanda tangan dan kedaluwarsa sesuai `auth.passwords.users.expire` (60 menit), token sekali pakai.
- Batas: 2 permintaan/menit per IP (Filament) dan 1 email/menit per alamat (throttle broker Laravel). Penggantian
  kata sandi juga dibatasi 2/menit. Setelah berhasil, sesi lain akun itu keluar karena `AuthenticateSession`
  membandingkan hash kata sandi.
- Audit log `auth`: event `password_reset_requested` (hanya bila email benar-benar dikirim) dan `password_reset`
  (dari event `PasswordReset`), dengan IP & user agent.

### Captcha halaman masuk petugas
- Komponen form `TurnstileCaptcha` (Turnstile mode explicit, token disimpan ke state Livewire tanpa request
  tambahan) dipasang di halaman masuk, lupa kata sandi, dan atur ulang kata sandi. Verifikasi memakai
  `TurnstileVerifier` yang sama dengan form publik, saat validasi form — sebelum kredensial diperiksa.
- Token Turnstile sekali pakai: halaman mengirim event `torang-turnstile-reset` setelah percobaan gagal agar widget
  meminta token baru.
- Wajib bila kunci terisi, dan selalu wajib di produksi (tanpa kunci → ditolak, sama dengan form publik; `torang:check`
  GAGAL). Di lokal/test tanpa kunci captcha dilewati agar pengembangan tetap bisa masuk.

### Logo di layar TV lab
- Logo Kemenkes Poltekkes Manado dan supergrafis Kemenkes (file dari pengguna, latar transparan) disimpan di
  `public/images/brand/`. Supergrafis hanya dekorasi (`aria-hidden`, opasitas rendah) agar teks tetap terbaca dari jauh;
  simbol TORANG pindah ke footer supaya header tidak memuat dua logo.

## Tambahan — Kabar tiket ke pelapor lewat WhatsApp & email (31 Oktober 2026)

- Kontak pelapor tetap satu isian bebas ("Email / WhatsApp"). Dianggap nomor WhatsApp bila setelah spasi/tanda baca
  dibuang cocok `^(0|62)8\d{7,12}$` (nomor HP Indonesia, dinormalkan ke 62…); dianggap email bila valid sebagai
  email; selain itu tidak ada tombol kabar (mis. nomor telepon kantor atau teks bebas).
- **Manual** di detail tiket (izin kelola tiket): satu tombol yang menyesuaikan kontak — nomor HP membuka
  `https://wa.me/62…?text=…` di tab baru (dikirim dari WhatsApp petugas/kantor, tanpa gateway; notifikasi berisi
  tombol cadangan bila pop-up diblokir), email dikirim dari mailer TORANG. Template mengikuti status tiket
  (belum selesai → "Tiket diproses", sudah selesai → "Tiket selesai") dan bisa disunting.
- **Otomatis** setelah transaksi tersimpan, hanya saat status berubah ke Dikerjakan atau Selesai: email selalu
  dikirim bila kontaknya email (sama dengan pemohon Lab CBT); WhatsApp lewat gateway hanya bila kanal WhatsApp aktif.
  Lewat antrean `default`.
- "Pekerjaan yang dilakukan" wajib saat status menjadi Selesai (maks. 2000 karakter), ditulis untuk pelapor; tampil
  di halaman publik hanya setelah tiket selesai.
- Tautan penilaian di pesan "selesai" memakai URL bertanda tangan (`tickets.review`, 14 hari) sehingga tidak perlu
  menyimpan token polos; tautan rahasia lama (hash) tetap berlaku. POST penilaian juga bertanda tangan dengan masa
  berlaku yang sama, tetap memakai captcha & honeypot. `InvalidSignatureException` dipetakan ke 403 berpesan
  "Tautan tidak valid atau sudah kedaluwarsa."
- Audit log `tickets` event `reporter_notified` dengan jenis pesan, kanal (chat WhatsApp / WhatsApp gateway / email),
  dan mode (manual/otomatis). Untuk chat WhatsApp yang tercatat adalah saat petugas membuka chat; pengiriman
  sebenarnya terjadi di aplikasi WhatsApp.

## Tambahan — Akun petugas, login NIP & kata sandi awal (5 November 2026)

- Atas permintaan pengguna, petugas masuk dengan NIP atau email dan kata sandi awal sama dengan NIP. NIP mudah
  diketahui (bahkan tertera di dokumen resmi), jadi pengamannya: wajib ganti saat login pertama, captcha, batas
  percobaan login, dan peringatan `torang:check`. Risiko tersisa ada di rentang antara impor dan login pertama.
- `users.must_change_password` ditegakkan middleware `EnsurePasswordChanged` pada panel dan rute unduhan; hanya
  halaman profil dan keluar yang boleh dibuka. Permintaan Livewire tidak dicegat karena tanpa halaman yang terbuka
  tidak ada komponen untuk dipanggil.
- Kata sandi baru minimal 12 karakter (sama dengan form Pengguna) dan tidak boleh memuat NIP, baik di profil maupun
  di atur ulang lewat email. Reset lewat email ikut melepas kewajiban ganti.
- Halaman profil memakai `EditProfile` Filament versi simple; nama, NIP, dan email hanya tampil (diubah admin lewat
  menu Pengguna) supaya halaman ini khusus kata sandi.
- Data pegawai asli tidak boleh masuk repo publik, jadi akun dibuat dari CSV privat lewat `torang:import-users`, bukan
  seeder. Kolomnya hanya `nip,nama,email,whatsapp,role`: NIK dan tanggal lahir dari DUK tidak disimpan. Berkas
  divalidasi utuh lebih dulu; satu baris salah membatalkan semuanya. Akun yang sudah ada tidak pernah ditimpa, dan NIP
  tidak ditampilkan di layar. Data Petugas dengan NIP yang sama otomatis disambungkan ke akun.

## Tambahan — Deployment Docker (6 November 2026)

- Server produksi dipakai bersama aplikasi lain, dengan Nginx host di port 80/443 dan Certbot. Jadi stack TORANG tidak
  membuka port publik: kontainer `web` hanya di localhost (`WEB_PORT`), lalu Nginx host menangani domain dan HTTPS.
  Rancangan awal dengan Caddy dibatalkan karena akan berebut port. Kode di `/opt/torang`, database dan jaringan Docker
  terpisah dari aplikasi lain.
- Karena semua lalu lintas lewat Nginx host, `TRUSTED_PROXIES=*` aman, dan Nginx host menimpa `X-Forwarded-For` dengan
  `$remote_addr` agar nilai kiriman pengunjung tidak dipercaya.
- PHP 8.4, versi yang dipakai seluruh test (`composer.json` tetap mengizinkan 8.3). MariaDB 11.4, bukan MySQL, karena
  `mysqldump` dari klien Debian adalah MariaDB dan tidak cocok dengan server MySQL 8.4 untuk backup.
- Satu image `torang-app` untuk PHP-FPM, worker, dan scheduler; kontainer berjalan sebagai `www-data`. Konfigurasi
  di-cache di entrypoint karena nilainya dari environment. `clear_env = no` diperlukan karena `TRUSTED_PROXIES` dibaca
  saat bootstrap. Nginx kontainer memakai DNS Docker agar tidak menyimpan alamat lama kontainer `app` setelah deploy.
- `.env` dan CSV akun tidak masuk image (`.dockerignore`). `APP_KEY` dan kata sandi database dibuat di server.
- Worker monitoring dan default dipisah seperti Supervisor; satu worker bersama membuat antrean `default` tertahan
  karena antrean `monitoring` tidak pernah kosong.

## Tambahan — Dua CBT Center (6 November 2026)

- Atas permintaan pengguna, data lab hanya dua: Kampus Malalayang dan Kampus Perkamil, menggantikan tiga lab contoh pada
  SPEC §16. Kode `LAB-1` dan `LAB-2` dipertahankan agar alamat layar TV dan relasi tidak berubah.
- `LabSeeder` memakai `firstOrCreate` agar menjalankan ulang seeder tidak menimpa kapasitas dan jumlah perangkat
  yang sudah diisi petugas.

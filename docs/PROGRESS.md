# Progres Pengerjaan TORANG

- [x] Fase 0 — Fondasi (Laravel, Filament, Inertia + React + TS, brand, role)
- [x] Fase 1 — Lab CBT & Pengajuan
- [x] Fase 2 — Jadwal Petugas
- [x] Fase 3 — Mesin Monitoring
- [x] Fase 4 — Beranda & Status Publik (React)
- [x] Fase 5 — WiFi & Pengingat Hosting
- [x] Fase 6 — Laporan UKOM (SPTJM)
- [x] Fase 7 — Helpdesk, Pengumuman, Laporan, Dashboard
- [x] Penyempurnaan beranda, petugas mingguan, tema Neumorphism & uji LAN
- [x] Tambahan: sakelar tampilan halaman depan, WiFi publik terpilih, under maintenance per monitor
- [x] Fase 8 — Finalisasi & Deployment VPS
- [x] Tambahan: dashboard bertab, Dashboard Eksekutif, layar TV per lab, palet Kemenkes, login & favicon

## Catatan

### Dashboard bertab, Dashboard Eksekutif, TV per lab — 31 Oktober 2026
- [x] Dashboard awal bertab: **Ringkasan** (kartu angka + "Perlu tindakan"), **Lab CBT** (kegiatan, pengajuan,
  petugas, tautan TV lab), **Jaringan & Aplikasi**, **Infrastruktur**, **Layanan**, **Halaman depan**. Tab tanpa izin
  tidak tampil; tab aktif di URL `?tab=`.
- [x] Palet Kemenkes (teal, lime, cyan, hijau) pada tab, kartu angka, garis aksen bagian, garis empat warna di
  bawah topbar, dan seri grafik.
- [x] **Dashboard Eksekutif** (`/admin/eksekutif`, izin baru `dashboard.executive` untuk Kepala Unit & Super Admin):
  filter periode, 6 indikator dibanding periode sebelumnya, insight & rekomendasi otomatis, grafik pemakaian lab,
  pengaduan jaringan vs insiden, waktu tanggap & penyelesaian tiket, uptime jaringan per jurusan.
- [x] Waktu tanggap tiket: kolom `first_responded_at` (tindak lanjut/komentar petugas pertama), tampil di detail tiket.
- [x] **Layar TV per lab** `/tv/lab/{kode}` tanpa login: sedang berlangsung, sesi berikutnya, sudah selesai hari ini,
  sesi hari berikutnya; tombol **Buka TV** di daftar lab dan tab Lab CBT.
- [x] Halaman login berwarna palet Kemenkes + "TORANG · Versi 1.1.0"; favicon diberi penanda versi agar ikon lama
  (cache browser dari proyek lain di localhost:8000) terganti.
- Verifikasi: `php artisan test` 460 lulus (42 test baru: dashboard, eksekutif, TV lab, waktu tanggap) · `pint`, TypeScript & build bersih · TV lab dicek di browser 1920×1080 dan
  375 px tanpa gulir horizontal · login mode terang & gelap · tab dashboard & Dashboard Eksekutif dirender di
  server dengan data lokal (akun admin tidak dipakai di browser). Setelah deploy jalankan `torang:sync-permissions`
  (otomatis di `deploy.sh`) agar izin `dashboard.executive` ada.

### Fase 8 — Finalisasi & Deployment VPS — selesai 30 Oktober 2026
- [x] Seeder data contoh lengkap §16 diuji end-to-end (`DatabaseSeederTest`: jumlah departemen, lab, booking,
  petugas, monitor + riwayat, WiFi, provider/layanan, tiket, pengumuman, kolegium, laporan UKOM final).
- [x] Review keamanan: header keamanan (nosniff, frame SAMEORIGIN, Referrer-Policy, Permissions-Policy, COOP,
  HSTS hanya HTTPS produksi) untuk halaman publik & panel; `TRUSTED_PROXIES`; HTTPS dipaksa di produksi;
  throttle login panel diuji.
- [x] Review performa: `preventLazyLoading` di luar produksi (semua halaman utama lolos tanpa N+1), pengaturan
  di-memo per request (query beranda dingin 68 → 41, hangat 22 → 9), index `activity_log.created_at` dan
  `monitor_incidents (monitor_id, resolved_at)`; `retry_after` queue 360 detik (> backup 300 detik).
- [x] Kode Fase 7 dirapikan tanpa mengubah perilaku: enum tiket/pengumuman memakai label & warna seragam,
  aksi tiket dipusatkan di `TicketActions`, service laporan & dashboard dipecah per bagian.
- [x] `php artisan torang:sync-permissions` (permission baru untuk role lama tanpa menimpa perubahan panel) dan
  `php artisan torang:check` (pemeriksaan kesiapan server, keluar gagal bila ada GAGAL) + detak scheduler.
- [x] `deploy.sh` + contoh `deploy/` (Nginx, Supervisor, cron, sysctl ping), README instalasi VPS Ubuntu
  24.04 langkah demi langkah, `.env.example` dengan panduan nilai produksi.
- Verifikasi: `php artisan test` 418 lulus (2141 assertions) · `pint` bersih · TypeScript & build bersih ·
  migrasi index dijalankan & di-rollback di MySQL lokal · `torang:check` lokal: siap dengan 4 peringatan
  (LibreOffice, `mysqldump` belum di PATH/`DB_DUMP_BINARY_PATH`, scheduler tidak berjalan, Telegram nonaktif).
- Belum diuji di VPS sungguhan: `deploy.sh`, konfigurasi Nginx/Supervisor/cron hanya diperiksa sintaksnya
  (`bash -n`) dan ditinjau manual.

### Tampilan halaman depan, WiFi publik & maintenance — 30 Oktober 2026
- Review commit ChatGPT (Fase 7, Neumorphism/LAN, Mode TV): tidak ada celah keamanan; diperbaiki tautan
  dashboard ke monitor jaringan/aplikasi (kini tautan "Buka" per kartu), bundel CA pada `start-lan.ps1`
  (verifikasi captcha di uji LAN), dan widget Turnstile yang sempat dirender dua kali.
- Dashboard: widget **Tampilan halaman depan** (sakelar Status jaringan, Status aplikasi, WiFi), tabel
  **Jaringan & aplikasi** (Maintenance/Selesai maintenance, Tampilkan/Sembunyikan dari depan per monitor), dan
  tabel **WiFi kampus** (pilih SSID tampil di depan dengan konfirmasi; password SSID terpilih terlihat langsung,
  lainnya tetap lewat "Lihat password").
- Under maintenance per monitor (juga di tabel & halaman detail monitor): keterangan + perkiraan selesai opsional,
  pengecekan/notifikasi berhenti, insiden terbuka ditutup, berakhir otomatis saat perkiraan selesai lewat.
  Halaman depan & /status menampilkan pill Maintenance beserta keterangannya.
- Beranda: bagian **WiFi kampus** (SSID, lokasi, password + tombol Salin) untuk SSID terpilih saat sakelar WiFi
  menyala; bagian jaringan/aplikasi (dan datanya di /status & API ringkasan) hilang saat disembunyikan.
- Verifikasi: `php artisan test` 403 lulus · `pint` bersih · TypeScript & build bersih · migrasi MySQL lokal ·
  beranda (WiFi, Maintenance) dicek di browser; dashboard dicek lewat render server (akun admin lokal tidak dipakai
  karena password-nya sudah diganti). Data demo lokal dikembalikan ke bawaan (WiFi tidak tampil).

### Akses Mode TV dari beranda — 29 Oktober 2026
- [x] Kartu **Mode TV / NOC** pada bagian layanan beranda, dengan tombol **Buka Mode TV**
  dan keterangan login petugas. Tautan menuju `/admin/tv`; izin akses yang sudah ada tetap berlaku.
- Verifikasi: 378 test lulus (1843 assertions), Pint, TypeScript, build, dan navigasi dari
  kartu beranda ke login petugas diperiksa di browser.

### Penyempurnaan UI & akses LAN — selesai 28 Oktober 2026
- Beranda: banner slider dengan panah, indikator, jeda, navigasi keyboard; kartu akses layanan,
  pelacakan pengajuan/tiket, ringkasan kondisi, kartu aplikasi, dan menu ponsel yang ringkas.
- Petugas minggu berjalan: kartu Senin–Sabtu berisi nama, shift, waktu WITA, dan cakupan lab.
  Hanya jadwal terbit; draf, kontak, NIP, serta catatan internal tidak dikirim ke publik.
- Neumorphism bersama untuk seluruh halaman publik dan panel/dashboard Filament, termasuk
  mode terang/gelap, permukaan timbul, input cekung, dan animasi masuk/hover/tekan.
  Pengaturan reduced motion mematikan animasi tambahan serta putaran otomatis slider.
- Form pengajuan dan captcha menyesuaikan perubahan lebar layar tanpa melebar horizontal.
- Skrip Windows untuk server uji LAN dan firewall TCP khusus IP, adapter, aplikasi PHP,
  serta subnet lokal. Konfigurasi lokal memakai APP_URL LAN dan APP_DEBUG=false.
  Panduan menjalankan/menghentikan layanan: `docs/LAN_TESTING.md`.
- Verifikasi: **378 test lulus (1843 assertions)**, Pint, TypeScript, dan build sukses.
  Beranda, pengajuan, kalender, status, helpdesk, pelacakan, serta dashboard dicek pada 360 px;
  dashboard dicek dalam mode terang/gelap. Beranda dan login melalui IP LAN merespons HTTP 200;
  aturan firewall dan listener diverifikasi. Uji dari perangkat fisik lain belum dilakukan.

### Fase 0 — selesai 23 Oktober 2026
- Laravel 13 + Filament 5 (`/admin`, tema TORANG) + Inertia 3/React 19/TypeScript untuk halaman publik.
- Role `super_admin`, `kepala_unit`, `petugas_it`, `pengelola_cbt` dengan 28 permission granular;
  akun super admin dari `ADMIN_EMAIL`/`ADMIN_PASSWORD`.
- `SettingsService` + 39 pengaturan default (`config/settings.php`).
- Beranda publik berupa layout kosong bergaya TORANG (header, footer, kontak helpdesk dari pengaturan).
- Verifikasi: `php artisan test` 32 lulus · `pint --test` bersih · `npx tsc --noEmit` bersih ·
  `npm run build` sukses · login admin dicek manual di browser.
- Keputusan teknis: lihat `docs/DECISIONS.md`.
- Belum ada di lokal: LibreOffice (`soffice`) untuk konversi PDF laporan UKOM (dibutuhkan Fase 6).

### Fase 1 — selesai 23 Oktober 2026
- Master departemen/unit, lab + jumlah perangkat per jenis (`Lab::readyPcCount()`).
- Pengajuan publik `/ajukan` (React): 4 kartu bernomor, cek ketersediaan lab live
  (`/api/public/availability`), upload PDF ke disk privat, honeypot, Cloudflare Turnstile,
  `throttle:5,1`; halaman `/ajukan/terkirim/{code}` dan `/cek-status` tanpa data pribadi.
- Aturan di `BookingRules`: bentrok (pending/approved), kapasitas PC siap pakai, lab aktif,
  H-3 (publik), jam operasional — dipakai form publik, panel admin, dan saat approve.
- Panel: Lab, Pengajuan (filter, approve/tolak/batal, aksi massal, unduh lampiran,
  log pemakaian), Kalender (FullCalendar, warna per lab), Log Pemakaian, Departemen/Unit.
- Notifikasi email pemohon (diterima/disetujui/ditolak/pengingat H-1) + Telegram grup
  (pengajuan baru, bila dikonfigurasi). Scheduler `bookings:mark-completed` (5 menit)
  dan `bookings:send-reminders` (16:00).
- Verifikasi: `php artisan test` 116 lulus · `pint --test` bersih · `npx tsc --noEmit` bersih ·
  `npm run build` sukses · alur pengajuan publik → admin dicek manual di browser
  (termasuk verifikasi Turnstile sungguhan dengan kunci uji Cloudflare).
- Ditunda: tombol "Buat tiket" dari log pemakaian (Fase 7), rekap & utilisasi lab (Fase 7),
  kalender publik `/jadwal` (Fase 4), kanal WhatsApp (bersama infrastruktur notifikasi Fase 3).

### Fase 2 — selesai 23 Oktober 2026
- Master Petugas (akun opsional, Telegram chat ID) dan Shift (default Pagi 07:30–12:00, Siang 12:00–16:00).
- Jadwal Mingguan: grid hari (Sen–Sab) × shift dengan tab cakupan (seluruh CBT Center / tiap lab),
  salin dari minggu lalu, peringatan kegiatan disetujui tanpa petugas, terbitkan / kembalikan ke draf
  (penerbitan ulang = revisi), unduh PDF A4 bertanda tangan Kepala Unit, rekap tugas & jam per bulan.
- Jadwal Saya untuk akun yang terhubung ke data petugas (jadwal terbit saja, dengan rekan jaga).
- Notifikasi Telegram ke petugas saat terbit dan pengingat H-1 (`duty:send-reminders` 16:00).
- `DutyRoster::onDuty()` siap dipakai kartu "Sedang berlangsung" di beranda (Fase 4).
- Verifikasi: `php artisan test` 156 lulus · `pint --test` bersih · `npx tsc --noEmit` bersih ·
  `npm run build` sukses · grid, Jadwal Saya, rekap, dan PDF dicek manual di browser.
- Catatan: nama & NIP Kepala Unit (`identity.head_name`/`head_nip`) belum punya halaman Pengaturan;
  sementara diisi lewat database/tinker.

### Tambahan (di luar SPEC, disetujui 23 Oktober 2026) — Master Pegawai dari DUK
- Menu Sistem › Pegawai (hanya baca) dengan tombol **Impor DUK** (.xlsx, sheet "LAPORAN DUK"):
  upsert berdasarkan NIP, pegawai yang hilang dari DUK/pensiun dinonaktifkan, file dihapus setelah diproses.
- Disimpan hanya nama, NIP, golongan, jabatan, jabatan struktural, unit/penempatan, email Kemenkes.
  NIK, tempat/tanggal lahir, telepon, dan email pribadi tidak disimpan.
- Nama & NIP Kepala Unit di Pengaturan diisi otomatis dari jabatan struktural
  `identity.head_structural_position` (default "Kepala Unit Teknologi Informasi").
- Form Petugas bisa memilih pegawai (nama/NIP/jabatan terisi); form pengajuan admin bisa mencari
  pemohon dari pegawai. Form publik tidak memakai data pegawai.
- Verifikasi: `php artisan test` 172 lulus · DUK Agustus 2026 asli diimpor ke database lokal
  (195 pegawai, 194 aktif, 1 pensiun) — file dan data asli tidak masuk repo.

### Fase 3 — selesai 23 Oktober 2026
- Monitor jaringan (ICMP/TCP) dan aplikasi (HTTP + SSL) dengan pengaturan per monitor atau global.
- Mesin: `monitors:dispatch` tiap menit → `CheckMonitorJob` di queue `monitoring`; checker ICMP
  (Symfony Process, argumen array, Linux/Windows), TCP, HTTP (kode normal, kata kunci, tanpa ikut redirect),
  SSL harian (peringatan H-30/14/7).
- State machine: gagal di bawah batas = Lambat ("Gagal 1/3"), Offline setelah N gagal → insiden dibuka;
  pulih → insiden ditutup + durasi. Notifikasi hanya saat transisi (grup Telegram + email PIC/petugas),
  cooldown, notifikasi Lambat > N menit opsional.
- `monitors:aggregate` (00:10) → statistik harian permanen; `model:prune` (01:00) → retensi detail cek.
- Panel Monitoring: Jaringan, Aplikasi (detail: cek sekarang, grafik 24 jam/7/30 hari, insiden, uptime bulanan),
  Insiden (catatan), Pengaturan (monitoring & kanal notifikasi).
- Kanal WhatsApp (gateway HTTP generik / driver log) untuk pemohon pengajuan dan petugas jaga; nonaktif default.
- `composer run dev` kini menjalankan worker `monitoring,default` dan `schedule:work`.
- Seeder contoh: 10 router jurusan/unit (IP dokumentasi) + 7 aplikasi, riwayat sintetis 7 hari.
- Verifikasi: `php artisan test` 234 lulus · `pint --test` bersih · ping sungguhan di Windows
  (127.0.0.1 Online, IP dokumentasi & host tak dikenal Offline) · jalur queue end-to-end · halaman admin dicek di browser.
- Ditunda: ekspor uptime bulanan untuk SLA ISP (Excel/PDF) → Fase 7.

### Fase 4 — selesai 24 Oktober 2026
- Beranda `/` (React): hero + kartu ringkasan (jam WITA, jaringan/aplikasi online, lab dipakai),
  kartu "Sedang berlangsung" per lab (kegiatan, sisa waktu, PC dipakai/siap, kegiatan berikutnya, petugas jaga),
  tabel jadwal minggu berjalan (hari ini disorot, geser horizontal di ponsel), kartu jaringan per jurusan
  dengan bar 24 jam, daftar aplikasi dengan bar 30 hari.
- `/status`: status keseluruhan, bar uptime harian 90 hari + uptime 30/90 hari, insiden 30 hari terakhir.
- `/jadwal`: kalender bulanan FullCalendar (bahasa Indonesia, warna per lab), tampilan daftar di ponsel,
  data dari `/api/public/events` (disetujui/selesai saja, rentang maks. 62 hari).
- `/api/public/summary` (JSON ringkas, `Cache-Control: max-age=30`) untuk mode TV/widget nanti.
- Semua data publik lewat `PublicStatus` (cache 30 detik); auto-refresh `usePoll` sesuai
  `public.refresh_seconds` dengan partial reload (jadwal minggu tidak ikut dimuat ulang).
- Privasi: tanpa IP/host/URL internal, tanpa nama/kontak pemohon — diuji untuk kelima endpoint publik.
- Verifikasi: `php artisan test` 252 lulus · `pint` bersih · `npx tsc --noEmit` bersih · `npm run build` sukses ·
  beranda, `/status`, `/jadwal` dicek di browser (desktop dan 360 px, tanpa scroll horizontal halaman).
- Perbaikan kecil Fase 1 saat uji 360 px: widget Turnstile memakai ukuran compact bila ruang < 300 px.
- Ditunda: banner pengumuman di beranda → Fase 7 (bersama modul Pengumuman).

### Fase 5 — selesai 24 Oktober 2026
- Infrastruktur › WiFi: SSID, lokasi, jurusan, AP, peruntukan, keamanan, VLAN, siklus ganti password.
  Password terenkripsi (`encrypted`), tidak pernah tampil di tabel/form/log; form edit tidak memuat password lama.
- "Lihat password" & "Kartu QR": konfirmasi password akun (berlaku 5 menit, maks. 5 percobaan salah/menit),
  modal password dengan tombol Salin; lihat/salin/cetak/ganti tercatat di activity log beserta IP & user agent
  (tab Log akses di halaman detail).
- Riwayat password: password lama tersimpan terenkripsi saat diganti, bisa dilihat dengan konfirmasi yang sama.
- Kartu QR WiFi PDF A6 (`WIFI:T:…;S:…;P:…;;`), memakai `chillerlan/php-qrcode`.
- `wifi:rotation-reminders` (Senin 08:00): ringkasan SSID yang jatuh tempo ≤ 7 hari/lewat ke grup Telegram
  dan email pengelola WiFi, tanpa password, sekali per minggu.
- Infrastruktur › Hosting & Domain + Provider (seed: Domainesia, Jagoanhosting, Hostinger): status turunan
  Aktif/Segera berakhir/Kedaluwarsa/Dihentikan, "Catat pembayaran" (bukti PDF privat, unduh ber-otorisasi),
  riwayat pembayaran, ringkasan (jumlah per status, segera jatuh tempo, estimasi biaya per tahun per provider).
- Ekspor Excel (Layanan, Anggaran per provider, Pembayaran per tahun) dan PDF rekap anggaran A4 lanskap.
- `services:send-reminders` (harian 08:00): H-30/14/7/3/1/0 ke grup Telegram, email PIC/pengelola hosting,
  Telegram/WhatsApp pribadi PIC; tanpa kirim ganda, mengejar ambang terlewat, siklus baru setelah perpanjangan.
- Seeder contoh: 4 SSID, 6 layanan dengan jatuh tempo bervariasi.
- Verifikasi: `php artisan test` 306 lulus · `pint` bersih · migrasi di MySQL lokal · alur lihat/salin password,
  log akses, halaman hosting dicek di browser · kartu QR & PDF anggaran dicek visual.

### Fase 6 — selesai 24 Oktober 2026
- Lab CBT › Penyelenggara/Kolegium (Kolegium TPD otomatis tersedia) dan Pengaturan UKOM (penanda tangan default,
  pola nomor surat, tempat surat, versi template Word).
- Lab CBT › Laporan UKOM: wizard 5 langkah (Kegiatan, Surat, Rincian biaya, Penanda tangan, Ringkasan) dengan
  nama kegiatan/hal/tujuan/teks rincian otomatis yang tetap bisa diedit, preset sewa CBT & konsumsi dari tarif kolegium,
  total langsung, ambil data dari pengajuan Lab CBT atau data petugas.
- Nomor surat otomatis per tahun (dipesan saat draf disimpan, tidak ganda, nomor manual yang mengikuti pola ikut
  dihitung); Finalkan mengunci isi & nomor; Duplikat; Unggah scan bertanda tangan; Arsipkan/Pulihkan.
- Dokumen: DOCX dari template (blok rincian di-clone, placeholder wajib terisi) di disk privat; PDF lewat LibreOffice
  di queue `default` dengan pratinjau iframe; tanpa LibreOffice hanya DOCX. Unduhan lewat route ber-otorisasi.
- Ganti template (Super Admin): validasi semua placeholder, disimpan sebagai versi baru; laporan mencatat versinya.
- Rekap Excel: daftar laporan per tahun surat + total biaya per tahun.
- Seeder contoh: 1 laporan final sesuai dokumen asli (2 rincian, total Rp 280.000,00), penanda tangan dari Pengaturan.
- Verifikasi: `php artisan test` 350 lulus · `pint` bersih · migrasi di MySQL lokal · wizard (isian otomatis, preset,
  total), halaman detail, pengaturan, dan kolegium dicek di browser · isi DOCX dicek otomatis oleh test.
- Belum bisa dicek di lokal: konversi PDF sungguhan (LibreOffice belum terpasang) — diuji dengan proses tiruan.

### Fase 7 — selesai 28 Oktober 2026
- Helpdesk: form publik `/lapor` dengan Turnstile, honeypot, rate limit, dan PDF privat maksimal 2 MB;
  kode tiket untuk `/cek-tiket`, tautan pribadi untuk tanggapan petugas dan penilaian 1–5 setelah selesai.
- Panel Tiket: laporan internal, lampiran, prioritas, penugasan petugas, transisi status, komentar internal/pelapor,
  dan penilaian. Tombol **Buat tiket** di Log Pemakaian mencegah tiket ganda dari log yang sama.
- Pengumuman: info/maintenance/insiden, rentang aktif, publik/internal; banner beranda diperbarui otomatis.
- Laporan CBT & SLA: filter periode/lab/penyelenggara, Excel dan PDF A4 lanskap; jumlah kegiatan, jam,
  peserta terdaftar/hadir, utilisasi, uptime tertimbang, dan insiden. Data kosong dinyatakan secara eksplisit.
- Dashboard operasional sesuai hak akses, ringkasan status monitor, insiden/hosting/tiket, grafik uptime 7 hari,
  serta `/admin/tv` berautentikasi dengan layar penuh dan pembaruan otomatis.
- Sistem: Pengguna, Role & hak akses, Pengaturan umum, Audit log, Backup database privat.
  Perlindungan admin terakhir/akun sendiri dan seeder yang mempertahankan perubahan izin role.
- Backup database di queue `default` pukul 02.00 WITA, pembersihan 02.30 WITA, retensi 14 hari;
  tersedia aksi backup manual dan unduhan berizin. `DB_DUMP_BINARY_PATH` mendukung lokasi program dump khusus.
- Verifikasi: `php artisan test` **374 lulus** · `pint` bersih · TypeScript bersih · build sukses.
  Dashboard, mode TV, tiket, laporan, banner, dan halaman publik diperiksa di browser; halaman publik muat
  pada viewport 360 px. Ekspor Excel berhasil dibuka; PDF CBT/SLA diperiksa visual.
- Backup MySQL sungguhan berhasil: ZIP privat berisi dump SQL, verifikasi arsip aktif. Akun uji sementara
  dibersihkan sebelum backup; data pegawai dan arsip backup tetap di lokal, tidak masuk repo.

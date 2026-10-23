# Progres Pengerjaan TORANG

- [x] Fase 0 — Fondasi (Laravel, Filament, Inertia + React + TS, brand, role)
- [x] Fase 1 — Lab CBT & Pengajuan
- [x] Fase 2 — Jadwal Petugas
- [x] Fase 3 — Mesin Monitoring
- [ ] Fase 4 — Beranda & Status Publik (React)
- [ ] Fase 5 — WiFi & Pengingat Hosting
- [ ] Fase 6 — Laporan UKOM (SPTJM)
- [ ] Fase 7 — Helpdesk, Pengumuman, Laporan, Dashboard
- [ ] Fase 8 — Finalisasi & Deployment VPS

## Catatan

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

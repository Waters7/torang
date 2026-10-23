# SPESIFIKASI TORANG
### Tata Operasional Ruang CBT, Aplikasi, Network & Gangguan
### UPT TIK — Poltekkes Kemenkes Manado

---

## 1. Ringkasan & Ruang Lingkup

TORANG adalah aplikasi web internal UPT TIK dengan dua wajah:

1. **Halaman publik (tanpa login, React + Inertia):** beranda berisi status layanan, Lab CBT yang sedang
   berlangsung, jadwal minggu berjalan, status jaringan per jurusan, status aplikasi kampus,
   form pengajuan pemakaian Lab CBT, dan cek status pengajuan.
2. **Panel admin (login, Filament):** pengelolaan lab, persetujuan pengajuan, jadwal petugas,
   monitoring, WiFi, pengingat hosting, helpdesk, laporan, dan pengaturan.

**Di luar ruang lingkup:** inventaris aset detail (kode aset, nomor seri, mutasi) dan
peminjaman barang — sudah ditangani unit Barang Milik Negara (BMN). TORANG hanya mencatat
**jumlah dan kondisi perangkat per lab** untuk kebutuhan penjadwalan.

---

## 2. Role & Hak Akses

| Role (`name`) | Keterangan | Akses utama |
|---|---|---|
| `super_admin` | Admin sistem | Semua, termasuk user, role, pengaturan |
| `kepala_unit` | Kepala UPT TIK | Menyetujui/menolak pengajuan, membuat & menerbitkan jadwal petugas, semua laporan, lihat WiFi |
| `petugas_it` | Petugas/teknisi IT | Monitoring, WiFi, pengingat hosting, tiket, isi log pemakaian, lihat "Jadwal Saya" |
| `pengelola_cbt` | Pengelola CBT Center | Kelola lab & perangkat, kelola pengajuan (tanpa approve final bila diatur), isi log pemakaian |
| *(publik)* | Tanpa login | Beranda, jadwal, status, ajukan pemakaian, cek status, lapor gangguan |

- Gunakan permission granular (mis. `bookings.approve`, `wifi.reveal-password`, `duty.publish`)
  lalu tempelkan ke role lewat seeder.
- Setting `bookings.approver_roles` menentukan siapa yang boleh approve (default: `kepala_unit`, `super_admin`).
- 2FA opsional untuk role `super_admin`, `kepala_unit`, `petugas_it`.

---

## 3. Modul A — Lab CBT Center

### 3.1 Data Lab
Nama, kode, lokasi/gedung/lantai, kapasitas, penanggung jawab (user), warna kalender (hex),
foto, status (`active` / `maintenance` / `inactive`), deskripsi.

### 3.2 Jumlah Perangkat per Lab (sederhana)
Per lab dicatat per jenis perangkat (PC Client, Server CBT, Switch, Access Point, UPS,
Proyektor, lainnya): `total`, `good`, `broken`, catatan.
Accessor `Lab::readyPcCount()` = jumlah `good` untuk jenis **PC Client** → dipakai validasi kapasitas.

### 3.3 Pengajuan Pemakaian (Booking)
**Sumber:** form publik (tanpa login) atau dibuat admin.

**Field:** kode unik `CBT-YYMM-XXXX`, lab, unit/jurusan penyelenggara, nama pemohon,
NIP/NIDN/NIM, email, no. WhatsApp, nama kegiatan, jenis kegiatan
(`exam` Ujian, `ukom_tryout` Try Out UKOM, `training` Pelatihan, `practicum` Praktikum,
`recruitment` Tes seleksi/rekrutmen, `maintenance` Maintenance — khusus admin, `other` Lainnya),
tanggal, jam mulai, jam selesai, jumlah peserta, kebutuhan tambahan, lampiran surat (PDF ≤ 2 MB),
persetujuan tata tertib.

**Validasi:**
- Tidak bentrok dengan booking `pending`/`approved` lain di lab yang sama
  (`start < other_end && end > other_start`).
- Jumlah peserta ≤ `readyPcCount` lab.
- Lab berstatus `active`; tanggal minimal H+`bookings.min_days_ahead` (default 3) untuk pengajuan publik.
- Jam dalam jam operasional (setting `bookings.open_time`/`close_time`, default 07:30–17:00).

**Alur status (enum `BookingStatus`):** `pending` → `approved` / `rejected` → `completed`;
`cancelled` bisa dari `pending`/`approved`.
- "Berlangsung" **tidak disimpan**, dihitung: `approved` dan `now` di antara jam mulai–selesai.
- Scheduler menandai `completed` setelah jam selesai lewat (atau saat log pemakaian diisi).
- Penolakan wajib mengisi alasan.

**Notifikasi pemohon (email + WhatsApp bila tersedia):** pengajuan diterima (berisi kode),
disetujui, ditolak (beserta alasan), pengingat H-1.
**Notifikasi internal (Telegram grup):** ada pengajuan baru.

**Admin:** tabel pengajuan dengan filter status/lab/tanggal, aksi approve/reject massal,
tampilan kalender (FullCalendar: bulan/minggu/hari, warna per lab), unduh lampiran.

### 3.4 Log Pemakaian
Diisi setelah kegiatan oleh petugas: jumlah peserta hadir, perangkat terpakai, perangkat
bermasalah, catatan kendala, pengisi. Tombol "Buat tiket" dari kendala → membuat tiket helpdesk
(sumber `usage_log`).

### 3.5 Laporan
Rekap per lab / bulan / semester / penyelenggara, jumlah kegiatan, total jam, peserta,
utilisasi (jam terpakai ÷ jam operasional tersedia). Export Excel & PDF.

---

## 4. Modul B — Jadwal Petugas IT CBT Center (Mingguan)

Dibuat oleh **Kepala Unit** setiap minggu.

- **Master Petugas (`staff`):** nama, NIP, jabatan, no. WhatsApp, Telegram chat ID, aktif,
  relasi opsional ke `users` (petugas tidak wajib punya akun).
- **Master Shift:** nama, jam mulai, jam selesai, urutan (default: Pagi 07:30–12:00, Siang 12:00–16:00).
- **Jadwal Minggu (`duty_weeks`):** `week_start` (Senin), status `draft` / `published`.
- **Penugasan (`duty_assignments`):** tanggal × shift × lab (nullable = seluruh CBT Center) × petugas.
  Satu slot boleh lebih dari satu petugas.

**Fitur:**
- Halaman Filament kustom berbentuk grid **hari (Sen–Sab) × shift**, tiap sel berisi multi-select petugas.
- "Salin dari minggu lalu", lalu edit.
- Peringatan: ada booking `approved` pada slot yang belum punya petugas.
- Terbitkan → notifikasi ke tiap petugas (Telegram/WhatsApp) + pengingat H-1 tugas.
- Menu "Jadwal Saya" untuk petugas yang login.
- Export PDF A4 siap tanda tangan (nama & NIP Kepala Unit dari pengaturan).
- Rekap jumlah tugas per petugas per bulan.
- Petugas jaga tampil otomatis di kartu "Sedang berlangsung" pada beranda publik.

---

## 5. Modul C & D — Monitoring Jaringan & Aplikasi

Satu mesin monitoring untuk dua grup:
- `network` — IP publik router tiap jurusan/unit (ICMP ping atau TCP port).
- `application` — website/aplikasi kampus (HTTP).

### 5.1 Data Monitor
Nama, grup, jurusan/unit (untuk network), tipe cek (`icmp` / `tcp` / `http`), host (IP/hostname),
port, URL, kategori aplikasi, ISP, bandwidth, kontak ISP, PIC, tampil di publik (bool),
urutan tampil, aktif.

**Pengaturan per monitor (null = pakai pengaturan global):** interval, timeout, jumlah paket ping,
batas gagal berturut-turut sebelum DOWN, ambang latency "Lambat", kode HTTP normal
(default `[200, 301, 302]`), kata kunci wajib di halaman, cek SSL.

### 5.2 Menu Pengaturan Monitoring (global)
Interval default (pilihan 1, 2, 5, 10, 15, 30 menit), timeout, batas gagal (default 3),
ambang latency Lambat (default 150 ms), ambang response time aplikasi Lambat (default 2000 ms),
cooldown notifikasi, channel notifikasi aktif, chat ID grup Telegram, retensi log detail (default 30 hari).

### 5.3 Mesin Pengecekan
- Command `monitors:dispatch` dijadwalkan **setiap menit** (`withoutOverlapping`): memilih monitor
  aktif yang jatuh tempo (`last_checked_at` null atau ≤ now − interval) lalu dispatch
  `CheckMonitorJob` ke queue `monitoring` (berjalan paralel).
- Interface `MonitorChecker` dengan implementasi:
  - `IcmpChecker` — `Process(['ping', '-c', $count, '-W', $timeout, $host])` (Linux;
    sediakan varian Windows `-n`/`-w` untuk development). Parse packet loss (%) dan rata-rata RTT (ms).
  - `TcpChecker` — `stream_socket_client("tcp://host:port", timeout)`; ukur waktu koneksi.
  - `HttpChecker` — Laravel HTTP Client (timeout, user-agent `TORANG-Monitor`): kode status,
    response time, pencarian kata kunci, pesan error.
  - `SslChecker` — baca masa berlaku sertifikat (`stream_socket_client` `ssl://` +
    `capture_peer_cert` + `openssl_x509_parse`), dijalankan harian.
- Hasil → `CheckResult` DTO (status, latency, packet_loss, http_status, error).

### 5.4 Status & Insiden
Enum `MonitorStatus`: `up` (Online), `degraded` (Lambat), `down` (Offline), `unknown`, `paused`.
- `degraded` bila latency/response time di atas ambang, atau packet loss > 0 dan < 100.
- `down` hanya setelah gagal berturut-turut ≥ batas gagal (`consecutive_failures`).
- Perubahan ke `down` → buka `monitor_incidents`; kembali `up`/`degraded` → tutup insiden
  dan hitung durasi.
- Notifikasi **hanya saat transisi** UP→DOWN dan DOWN→UP (berisi nama, jurusan, waktu, durasi),
  dengan cooldown. Opsional: notifikasi `degraded` > N menit.
- Peringatan SSL H-30, H-14, H-7 untuk monitor aplikasi.

### 5.5 Data Historis
- `monitor_checks` (detail tiap cek) — model `Prunable`, dihapus setelah retensi (default 30 hari).
- Job harian `monitors:aggregate` (00:10) → `monitor_daily_stats` (permanen): total cek, up,
  degraded, down, rata-rata latency, uptime %.
- **Bar 24 jam** (beranda): kelompokkan `monitor_checks` per jam; warna = status terburuk pada jam itu;
  abu-abu bila tidak ada data.
- **Bar 30/90 hari**: dari `monitor_daily_stats` (hijau ≥ 99,5%, kuning ≥ 95%, merah < 95%,
  abu-abu tanpa data — ambang bisa diatur).
- Admin: grafik latency/response time (24 jam, 7 hari, 30 hari), daftar insiden,
  uptime bulanan per monitor untuk evaluasi SLA ISP (export Excel/PDF).

> **Catatan penempatan server:** server TORANG sebaiknya berada di luar jaringan kampus
> (VPS/cloud) supaya tetap bisa mendeteksi dan mengirim notifikasi saat jaringan kampus down.

---

## 6. Modul E — SSID & Password WiFi (khusus petugas)

Akses: `super_admin`, `kepala_unit`, `petugas_it` (permission `wifi.view`, `wifi.reveal-password`, `wifi.manage`).

- **Data:** SSID, lokasi/gedung, jurusan/unit, router/AP, peruntukan (mahasiswa, dosen, staf, tamu,
  CBT, lainnya), keamanan (WPA2/WPA3/WPA2-WPA3/Open), password (`encrypted` cast), VLAN,
  siklus ganti password (hari), tanggal terakhir diganti, catatan, aktif.
- Password **tidak pernah** tampil di tabel. Tombol "Lihat password" memerlukan konfirmasi ulang
  password akun (`password.confirm`, berlaku 5 menit); tombol "Salin".
- Setiap lihat/salin/ubah dicatat di activity log (user, waktu, IP, user agent).
- Riwayat password lama tersimpan terenkripsi.
- Pengingat ganti password berkala (default 90 hari) ke petugas.
- Opsional: generate QR Code WiFi (format `WIFI:T:WPA;S:<ssid>;P:<pass>;;`) untuk dicetak — hanya petugas.

---

## 7. Modul F — Pengingat Hosting, VPS, Domain & Cloud

- **Provider** (ditambah sendiri oleh petugas; seed awal: **Domainesia, Jagoanhosting, Hostinger**):
  nama, website, URL panel, kontak support, catatan.
- **Layanan:** provider, nama layanan, jenis (`domain`, `vps`, `shared_hosting`, `cloud_server`,
  `ssl`, `email_hosting`, `other`), identitas (nama domain/paket), email akun terdaftar,
  dipakai oleh aplikasi apa, PIC, **tanggal pembayaran terakhir**, **berlaku sampai**,
  siklus tagihan, biaya, perpanjangan otomatis (bool), status (`active`/`stopped`), catatan.
- **Status turunan:** Aktif / Segera berakhir (≤ 30 hari) / Kedaluwarsa / Dihentikan.
- **Pengingat** harian 08:00 WITA (`services:send-reminders`) pada H-30, H-14, H-7, H-3, H-1, H-0
  (daftar hari bisa diatur) ke Telegram/Email/WhatsApp. Gunakan `reminder_logs` agar tidak terkirim ganda.
- **Perpanjangan:** form "Catat pembayaran" (tanggal bayar, periode baru, nominal, bukti bayar)
  → memperbarui `last_paid_at` & `expires_at`, riwayat tersimpan di `service_payments`.
- **Dashboard:** layanan yang segera jatuh tempo, total biaya per tahun per provider.
  Export Excel/PDF untuk penganggaran.

---

## 8. Modul G — Helpdesk, Pengumuman & Pendukung

1. **Helpdesk / tiket:** form "Lapor gangguan" publik + internal. Kategori (jaringan, akun, perangkat,
   aplikasi, CBT, lainnya), prioritas, status (`open`, `in_progress`, `waiting`, `resolved`, `closed`),
   penugasan petugas, komentar, lampiran, kode tiket untuk cek status publik,
   rating kepuasan setelah selesai.
2. **Pengumuman & maintenance terjadwal:** judul, isi, tipe (info/maintenance/insiden), rentang waktu,
   tampil di beranda publik sebagai banner selama aktif.
3. **Knowledge base / FAQ** (fase akhir, opsional): panduan WiFi, LMS, SIAKAD, ujian CBT.
4. **Audit log** aktivitas penting (spatie/laravel-activitylog).
5. **Backup otomatis** database harian (spatie/laravel-backup), simpan 14 hari.

---

## 8A. Modul H — Laporan Pertanggungjawaban UKOM (SPTJM)

### 8A.1 Tujuan
Membuat paket dokumen pertanggungjawaban biaya operasional pelaksanaan Uji Kompetensi (UKOM)
di CBT Center sesuai format resmi. Hasilnya satu file Word/PDF tiga halaman berkop Poltekkes:
1. **Surat pengantar** — Nomor, Lampiran, Hal, Kepada Yth., isi, tanda tangan.
2. **Laporan Penggunaan Dana Operasional** — judul kegiatan, jumlah biaya, rincian penggunaan, total.
3. **Surat Pernyataan Tanggung Jawab Mutlak (SPTJM).**

Template siap pakai: `resources/templates/laporan-pertanggungjawaban-ukom.docx`
(sudah diuji dengan PhpWord `TemplateProcessor`). Contoh kode pengisian ada di
`docs/reference/fill-template-example.php`, dan contoh hasilnya di
`docs/reference/contoh-hasil-laporan-ukom.pdf`.

### 8A.2 Menu & akses
**Lab CBT › Laporan UKOM** (permission `reports.ukom.manage`; role `super_admin`, `kepala_unit`,
`pengelola_cbt`). Submenu **Penyelenggara/Kolegium** untuk master data.

### 8A.3 Master Penyelenggara / Kolegium (`exam_organizers`)
Dipakai ulang untuk kolegium lain. Contoh seed: **Kolegium TPD**.
- Nama instansi tujuan (mis. `Kolegium TPD`)
- Jabatan tujuan default (mis. `Ketua Panitia Ujian Kompetensi Nasional`)
- Pihak penyelenggara dalam SPTJM (mis. `Tim Ad Hoc Kolegium TPD`)
- Rekening pengembalian: nama rekening, bank, nomor rekening, atas nama
- Nama anggaran default (mis. `UP UK Nakes Tahun 2022`), tahun pedoman keuangan default (mis. `2022`)
- Tarif default: sewa CBT per unit, konsumsi per orang per hari (untuk preset rincian)

### 8A.4 Form laporan — wizard 5 langkah (elemen dipisah)
**Langkah 1 · Kegiatan**
- *Ambil dari pengajuan Lab CBT* (opsional): pilih booking `approved`/`completed` → isi otomatis
  tanggal, lab, penyelenggara/prodi.
- Jenis ujian (default `Uji Kompetensi Nasional`) · Periode (angka) · Jenjang & prodi
  (mis. `D-3 Teknologi Bank Darah`) · Tanggal pelaksanaan · Penyelenggara/Kolegium (select).
- **Nama kegiatan** otomatis: `{jenis} Periode {periode} Mahasiswa {jenjang_prodi}`, bisa diubah manual.

**Langkah 2 · Surat**
- Nomor surat: otomatis dari pola `reports.ukom.number_pattern`
  (default `PP.06.03/F.XXX.20/{seq:3}/{year}`, nomor urut per tahun), tetap bisa diedit.
- Tempat surat (default `Manado`) · Tanggal surat (default hari ini).
- Jumlah lampiran (angka, default 2) → terbilang otomatis (`dua`).
- Hal: otomatis `Laporan dan Bukti Pertanggungjawaban atas Biaya Operasional {nama_kegiatan} Tanggal {tanggal_kegiatan}`, bisa diedit.
- Kepada: jabatan tujuan (otomatis `{jabatan default} Periode {periode} Mahasiswa {jenjang_prodi}`),
  instansi tujuan (dari kolegium), "Di" (default `tempat`).

**Langkah 3 · Rincian biaya** (repeater, bisa diurutkan)
- Per baris: uraian (mis. `Pembayaran Sewa CBT`, `Konsumsi`), volume 1 + satuan 1 (`2 unit`, `2 orang`),
  volume 2 + satuan 2 opsional (`2 hari`), keterangan opsional (`H-1 dan H`), harga satuan.
- **Jumlah** otomatis = volume 1 × (volume 2 atau 1) × harga satuan.
- **Teks baris** dibuat otomatis lalu bisa diedit sebelum dicetak, contoh:
  `Pembayaran Sewa CBT 2 unit (Rp 40.000/unit)` · `Konsumsi 2 orang untuk 2 hari (H-1 dan H) Rp 50.000,00/org`.
- Tombol preset cepat: "Sewa CBT per unit", "Konsumsi per orang per hari" (tarif dari kolegium).
- **Total** dihitung live dan ditampilkan di bawah repeater. Minimal 1 baris, total > 0.

**Langkah 4 · Penanda tangan & pernyataan** (default dari Pengaturan `reports.ukom.signer_*`,
atau pilih dari data petugas)
- Nama · NIP (18 digit) · Jabatan lengkap (mis. `Koordinator CBT Poltekkes Kemenkes Manado`) ·
  Label tanda tangan (mis. `Koordinator CBT`) · Alamat baris 1 & 2.
- Peran penanggung jawab (default `Penanggung Jawab Lokasi TUK/Koordinator CBT`).
- Nama anggaran, tahun pedoman, pihak penyelenggara, data rekening — diisi otomatis dari kolegium
  lalu **disalin (snapshot) ke laporan**, supaya laporan lama tidak berubah saat master diubah.

**Langkah 5 · Pratinjau & unduh**
- Pratinjau PDF (iframe), tombol **Unduh Word (.docx)** dan **Unduh PDF**.
- Aksi: Simpan draf · **Finalkan** (mengunci isi & nomor) · **Duplikat** (untuk prodi/periode berikutnya) ·
  **Unggah scan bertanda tangan** (PDF) · Arsipkan.

**Daftar laporan:** nomor, kegiatan, tanggal, total, status (`draft` / `final` / `signed`), filter tahun & prodi,
rekap total biaya per tahun (export Excel).

### 8A.5 Pemetaan placeholder template
| Placeholder | Sumber / format |
|---|---|
| `${nomor_surat}` | nomor surat |
| `${tempat_surat}`, `${tanggal_surat}` | `Manado`, `5 September 2026` (format `j F Y`) |
| `${lampiran_jumlah}`, `${lampiran_terbilang}` | `2`, `dua` |
| `${hal}` | hal surat |
| `${tujuan_jabatan}`, `${tujuan_instansi}`, `${tujuan_tempat}` | Kepada Yth. |
| `${nama_kegiatan}` | `Uji Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah` |
| `${tanggal_kegiatan}` | `05 September 2026` (format `d F Y`) |
| `${nama_instansi}` | `Poltekkes Kemenkes Manado` (Pengaturan identitas) |
| `${total_biaya}` | `Rp 280.000,00` |
| `${judul_ujian}` | huruf kapital `{jenis} Periode {periode}` → `UJI KOMPETENSI NASIONAL PERIODE 2` |
| `${judul_prodi}` | huruf kapital jenjang & prodi → `D-3 TEKNOLOGI BANK DARAH` |
| blok `${rincian}` … `${/rincian}` berisi `${rincian_no}`, `${rincian_uraian}`, `${rincian_jumlah}` | di-clone per baris rincian |
| `${ttd_label}`, `${ttd_nama}`, `${ttd_nip}` | blok tanda tangan (ketiga halaman) |
| `${ttd_jabatan}`, `${ttd_alamat_1}`, `${ttd_alamat_2}` | identitas pada SPTJM |
| `${peran_penanggung_jawab}`, `${nama_anggaran}`, `${tahun_pedoman}`, `${pihak_penyelenggara}` | isi SPTJM |
| `${rekening_nama}`, `${rekening_bank}`, `${rekening_nomor}`, `${rekening_atas_nama}` | rekening pengembalian |

### 8A.6 Pembuatan dokumen
- Service `UkomReportDocumentService`:
  `Settings::setOutputEscapingEnabled(true)` → `new TemplateProcessor($template)` →
  `cloneBlock('rincian', 0, true, false, $rows)` → `setValues([...])` → pastikan
  `getVariables()` kosong (jika tidak, lempar exception) → simpan ke
  `storage/app/private/reports/ukom/{id}/{slug}.docx`.
- PDF: konversi DOCX dengan LibreOffice lewat `Process(['soffice', '--headless', '--convert-to', 'pdf', '--outdir', $dir, $docx])`
  (timeout 60 detik, env `HOME` ke folder yang bisa ditulis), dijalankan di queue `default`.
  Jika LibreOffice tidak tersedia, tombol PDF dinonaktifkan dan hanya DOCX yang tersedia.
- Helper bersama: `Rupiah::format()` (`Rp 280.000,00`), `Terbilang::id()`, format tanggal Indonesia.
- **Template bisa diganti:** Pengaturan › Template Dokumen → unggah .docx baru, validasi semua placeholder
  wajib ada (lewat `getVariables()`), simpan sebagai versi baru. Setiap laporan mencatat versi template yang dipakai.

### 8A.7 Perbaikan dari dokumen contoh (sudah diterapkan di template)
- NIP di halaman 2 (`198203302010121003`) berbeda dari halaman 1 & 3 (`199310182025061003`)
  → sekarang memakai satu isian `${ttd_nip}`.
- Salah ketik "Dengat hormat" → "Dengan hormat"; "Mahasiswa Mahasiswa" → satu kata;
  "Manado. Ingin" → "Manado ingin".
- Label tanda tangan "Koord CBT"/"Koordinator CBT" disatukan menjadi `${ttd_label}`.
- Tahun pedoman dan nama anggaran ("2022") dijadikan isian.
- Surat pengantar menyebut lampiran **SPTB**, tetapi dokumen contoh belum memuat halaman SPTB.
  SPTB dilampirkan terpisah sampai formatnya ditambahkan ke template.

---

## 9. Halaman Publik (tanpa login)

### 9.0 Teknologi halaman publik
- **React + TypeScript** dirender lewat **Inertia.js** (tanpa SPA terpisah, routing tetap di Laravel).
  Controller mengembalikan `Inertia::render('public/Home', [...])` dengan data yang sudah disaring
  (API Resource) — tidak ada IP, SSID, email/telepon pemohon.
- Struktur: `resources/js/pages/public/{Home,Schedule,BookingCreate,BookingSent,BookingStatus,StatusPage,TicketCreate}.tsx`,
  layout `resources/js/layouts/PublicLayout.tsx`, komponen `resources/js/components/{TorangMark,StatusPill,UptimeBars,LabNowCard,WeekScheduleTable,...}.tsx`,
  tipe di `resources/js/types/public.ts`.
- **Auto-refresh:** `usePoll(refreshSeconds * 1000, { only: ['summary', 'labsNow', 'networks', 'apps'] })`
  dari Inertia (partial reload), atau fetch ke `/api/public/summary` bila halaman perlu polling ringan.
- Form pengajuan memakai `useForm` Inertia (validasi server ditampilkan per field, upload dengan progress).
- Kalender publik `/jadwal`: FullCalendar React (`@fullcalendar/react`).
- SSR Inertia opsional (tidak wajib untuk tahap awal).

### 9.1 Route
| Method | URI | Nama | Keterangan |
|---|---|---|---|
| GET | `/` | `home` | Beranda |
| GET | `/jadwal` | `schedule` | Kalender bulanan publik (approved + maintenance) |
| GET | `/ajukan` | `bookings.create` | Form pengajuan |
| POST | `/ajukan` | `bookings.store` | `throttle:5,1`, captcha, honeypot |
| GET | `/ajukan/terkirim/{code}` | `bookings.sent` | Halaman sukses + kode |
| GET | `/cek-status` | `bookings.status` | Query `?kode=`; tampilkan status tanpa data pribadi |
| GET | `/status` | `status` | Status page lengkap (riwayat 90 hari + insiden) |
| GET/POST | `/lapor` | `tickets.public` | Lapor gangguan |
| GET | `/api/public/summary` | — | JSON ringkasan, cache 30 detik, `throttle:60,1` |
| GET | `/api/public/availability` | — | `?date=&start=&end=` → ketersediaan tiap lab (untuk form), `throttle:30,1` |

### 9.2 Beranda — susunan dari atas ke bawah
1. **Header:** logo TORANG (simbol + wordmark + "POLTEKKES KEMENKES MANADO"), menu
   *Jadwal CBT*, *Status Jaringan*, *Status Aplikasi*, tombol utama **Ajukan Pemakaian**,
   tombol outline **Masuk Petugas**. Menu turun ke baris baru di mobile.
2. **Banner pengumuman** (bila ada pengumuman/maintenance aktif).
3. **Hero (2 kolom, menumpuk di mobile):**
   - Kiri: pill "Diperbarui HH:MM WITA · cek otomatis tiap N menit", judul
     "Pusat layanan IT Poltekkes Kemenkes Manado", deskripsi singkat, tombol
     **Ajukan Pemakaian Lab CBT →** dan **Lihat Jadwal Minggu Ini**.
   - Kanan: kartu gelap ringkasan dengan tanggal & jam WITA, lalu tiga baris yang bisa diklik
     ke section terkait: *Jaringan jurusan* `X / Y online` (+ "n lambat · n offline"),
     *Aplikasi kampus* `X / Y normal`, *Lab CBT saat ini* `X / Y dipakai`
     (+ "n tersedia · n maintenance").
4. **Sedang berlangsung** (`#jadwal`): grid kartu per lab.
   - **Berlangsung:** nama kegiatan, penyelenggara, progress bar waktu (jam mulai — "sisa N menit" —
     jam selesai), perangkat dipakai/total, petugas jaga. Kartu diberi border teal.
   - **Tersedia:** "Kosong hingga HH:MM" + kegiatan berikutnya hari ini, atau
     "Tidak ada kegiatan lagi hari ini"; tautan "Ajukan di lab ini →" (prefill lab).
   - **Maintenance:** keterangan + perkiraan selesai.
   - Petugas jaga diambil dari `duty_assignments` (tanggal hari ini, shift yang mencakup jam sekarang,
     lab tersebut atau lab null).
5. **Jadwal minggu berjalan:** tabel lab (baris) × hari Senin–Sabtu (kolom; Minggu opsional lewat
   pengaturan). Kolom hari ini disorot. Blok kegiatan berisi jam (font mono) + nama kegiatan ·
   penyelenggara singkat. Gaya: *Selesai* (abu-abu, redup), *Berlangsung* (hijau muda + border teal
   + "· sekarang"), *Terjadwal* (cyan muda), *Maintenance* (kuning muda). Legenda di kanan atas.
   Di mobile tabel bisa digeser horizontal. Hanya booking `approved` dan `maintenance`.
6. **Status jaringan per jurusan** (`#jaringan`): ringkasan pill (n Online / n Lambat / n Offline),
   grid kartu per monitor grup `network` yang `is_public`: nama jurusan, pill status,
   **bar 24 jam** (24 batang), latency (atau "loss X%" / "down sejak HH:MM"),
   "uptime 30 hari X%". Catatan kecil: "Alamat IP tidak ditampilkan untuk publik."
7. **Status aplikasi kampus** (`#aplikasi`): daftar baris per monitor grup `application`:
   nama + kategori, **bar 30 hari**, response time, uptime, pill status.
8. **Footer gelap:** TORANG + kepanjangan, "Dikelola oleh UPT TIK", kontak helpdesk (WhatsApp, email,
   jam layanan dari pengaturan), tautan cepat.

**Pembaruan data:** halaman React memakai `usePoll` Inertia sesuai setting
`public.refresh_seconds` (default 60) dengan partial reload. Jam di kartu ringkasan berjalan di sisi klien
(zona WITA). Query diringkas dan di-cache (30–60 detik) agar ringan.

### 9.3 Form Ajukan Pemakaian
Layout 2 kolom (form + sidebar, menumpuk di mobile):
- Kartu bernomor: **1 · Data pemohon**, **2 · Detail kegiatan**, **3 · Pilih lab**
  (kartu radio per lab: kapasitas, PC siap pakai, ketersediaan pada tanggal & jam terpilih — dicek
  live lewat `/api/public/availability` dengan debounce, lab yang bentrok diberi label
  "Terpakai HH:MM–HH:MM" dan dinonaktifkan; validasi final tetap di server),
  **4 · Lampiran & persetujuan** (upload PDF, checkbox tata tertib, captcha, tombol **Kirim Pengajuan**).
- Sidebar: kartu gelap **Ketentuan** (bernomor 01–04: H-3, kapasitas, surat permohonan,
  petugas sesuai jadwal jaga), kartu **Cek status pengajuan** (input kode), tautan ke jadwal.
- Sukses: halaman dengan pill "Pengajuan terkirim", kode pengajuan besar (font mono, kotak gelap),
  tombol kembali ke beranda dan ajukan kegiatan lain.

---

## 10. Panel Admin (Filament)

- **Tema:** warna primer `#08796C`, font Sora, logo TORANG, favicon simbol.
- **Dashboard (widget):** kegiatan CBT hari ini, petugas bertugas hari ini, pengajuan pending,
  monitor online/lambat/offline, aplikasi bermasalah, layanan hosting jatuh tempo ≤ 30 hari,
  tiket terbuka, insiden terbaru, grafik uptime 7 hari.
- **Navigasi grup:** Lab CBT (Lab, Pengajuan, Kalender, Log Pemakaian, Laporan,
  **Laporan UKOM**, Penyelenggara/Kolegium) · Petugas
  (Jadwal Mingguan, Jadwal Saya, Petugas, Shift) · Monitoring (Jaringan, Aplikasi, Insiden, Pengaturan) ·
  Infrastruktur (WiFi, Hosting & Domain, Provider) · Layanan (Tiket, Pengumuman, FAQ) ·
  Sistem (Pengguna, Role, Pengaturan, Audit Log, Backup).
- **Mode TV / NOC** (`/admin/tv`, atau route ber-token untuk layar tanpa login): layar penuh gelap,
  auto-refresh, berisi grid status jaringan, status aplikasi, dan kegiatan CBT hari ini.

---

## 11. Desain & Brand

### 11.1 Warna (palet turunan logo UPT Poltekkes — Pedoman Identitas Kemenkes)
| Token | HEX | CMYK | Pemakaian |
|---|---|---|---|
| `teal` | `#04B6A3` | C75 M0 Y45 K0 | Simbol, progress bar, border aktif |
| `lime` | `#D4E022` | C20 M0 Y100 K0 | Aksen (titik "online" pada simbol, nomor ketentuan) — jangan untuk teks di latar putih |
| `cyan` | `#1BBBCB` | C74 M0 Y21 K0 | Simbol, status "Tersedia/Terjadwal" |
| `green` | `#42B97C` | C69 M0 Y68 K0 | Simbol, status Online |
| `teal-text` | `#08796C` | — | Teks/tombol primer (kontras aman di putih) |
| `ink` | `#0F2E2B` | — | Teks utama |
| `ink-dark` | `#0B1F1D` | — | Latar gelap (kartu ringkasan, footer, mode TV) |
| `muted` | `#3F5A56` / `#5A6E6C` | — | Teks sekunder |
| `surface` | `#F4F8F7` | — | Latar halaman |
| `border` | `#DCEDEA` | — | Garis kartu |

**Warna status (selalu disertai label teks, jangan warna saja):**
| Status | Titik | Latar pill | Teks pill |
|---|---|---|---|
| Online / Berlangsung | `#42B97C` | `#E8F7F3` | `#1E6B45` |
| Lambat / Maintenance | `#E9A23B` | `#FFF4E0` | `#8A5A00` |
| Offline | `#D64545` | `#FDECEC` | `#B42318` |
| Tersedia / Terjadwal | `#1BBBCB` | `#E4F6F9` | `#0B5C66` |
| Tanpa data | `#C9D6D4` | `#EEF3F2` | `#4F6764` |

Daftarkan semua sebagai token di konfigurasi Tailwind (CSS variables) dan tema Filament.

### 11.2 Tipografi
- **Sora** 400/500/600/700/800 — judul, UI, wordmark.
- **JetBrains Mono** 500/600 — jam, angka metrik, kode pengajuan, label kecil kapital
  ber-letter-spacing (mis. "MONITORING · PING TIAP 1 MENIT").
- Judul section 28px/700; label eyebrow mono 12px, letter-spacing 0.14em, warna `teal-text`.
- Sudut membulat: kartu 14–20px, tombol & input 10–12px, pill 999px.

### 11.3 Logo
**Simbol:** huruf **T** dari modul-modul piksel pada grid 3×3, plus titik lime ("online").
Simpan sebagai `resources/svg/torang-mark.svg`, komponen React `TorangMark.tsx` (halaman publik),
dan komponen Blade `<x-torang-mark />` (panel Filament & PDF):

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" role="img" aria-label="TORANG">
  <!-- modul samar grid (opsional, hilangkan untuk ukuran kecil) -->
  <rect x="0"  y="35" width="26" height="26" rx="8" fill="#E3F5F2"/>
  <rect x="70" y="35" width="26" height="26" rx="8" fill="#E3F5F2"/>
  <rect x="0"  y="70" width="26" height="26" rx="8" fill="#E3F5F2"/>
  <rect x="70" y="70" width="26" height="26" rx="8" fill="#E3F5F2"/>
  <!-- huruf T -->
  <rect x="0"  y="0"  width="26" height="26" rx="8" fill="#1BBBCB"/>
  <rect x="35" y="0"  width="26" height="26" rx="8" fill="#04B6A3"/>
  <circle cx="83" cy="13" r="13" fill="#D4E022"/>
  <rect x="35" y="35" width="26" height="26" rx="8" fill="#04B6A3"/>
  <rect x="35" y="70" width="26" height="26" rx="8" fill="#42B97C"/>
</svg>
```
- Pada latar gelap, modul samar memakai `rgba(255,255,255,0.08)`.
- **Ikon/favicon:** simbol tanpa modul samar di atas kotak membulat `#0B1F1D` (rx 30 pada viewBox 128).
  Buat `favicon.svg`, `favicon.ico`, dan `apple-touch-icon.png`.
- **Wordmark lengkap:** simbol di kiri; di kanan huruf kapital **T O R A N G** (Sora 700), tiap huruf di
  kolom selebar sama. Di bawah tiap huruf ada garis aksen 18×3px (warna bergantian: cyan, teal,
  lime, cyan, teal, green), lalu kata kepanjangannya (JetBrains Mono 10px, kapital):
  TATA · OPERASIONAL · RUANG CBT · APLIKASI · NETWORK · GANGGUAN. Kemudian garis tipis dan
  "POLTEKKES KEMENKES MANADO" (mono, letter-spacing 0.22em, warna `teal-text`) di tengah.
- **Wordmark ringkas** (header, ukuran kecil): simbol + "TORANG" (Sora 800, letter-spacing 0.12em)
  + "POLTEKKES KEMENKES MANADO" (mono 10px). Jangan memakai kepanjangan di ukuran kecil.

### 11.4 Prinsip UI
- Bersih, modern-digital, banyak ruang putih; kartu putih dengan border tipis, tanpa bayangan berat.
- Hindari gradien dan emoji. Ikon pakai Heroicons (outline).
- Aksesibel: kontras teks ≥ 4.5:1, target sentuh ≥ 44px, `<button>`/`<a>`/`<label>` asli,
  status selalu disertai teks.
- Responsif mulai 360px: grid kartu `auto-fit/minmax`, tabel jadwal bisa digeser horizontal.

---

## 12. Skema Database (ringkas)

> Semua tabel memakai `id`, `timestamps`. Tambahkan foreign key, index pada kolom waktu dan FK.

- **users**: name, email, password, phone, telegram_chat_id?, is_active
- **departments**: name, short_name, type (`department`/`unit`), sort_order
- **labs**: code, name, location, capacity, color, status, pic_user_id?, photo_path?, description?
- **lab_device_counts**: lab_id, device_type, total, good, broken, notes?
- **bookings**: code (unique), lab_id, department_id?, applicant_name, applicant_identity_number,
  applicant_email, applicant_phone, activity_name, activity_type, date, start_time, end_time,
  participants_count, extra_needs?, attachment_path?, status, source (`public`/`admin`),
  approved_by?, approved_at?, rejection_reason?, softDeletes · index (lab_id, date, status)
- **usage_logs**: booking_id (unique), attendees_count, devices_used, devices_problem, notes?, filled_by
- **staff**: user_id?, name, nip?, position?, phone?, telegram_chat_id?, is_active
- **shifts**: name, start_time, end_time, sort_order, is_active
- **duty_weeks**: week_start (unique, Senin), status, published_at?, created_by, notes?
- **duty_assignments**: duty_week_id, date, shift_id, lab_id?, staff_id, notes? · unique gabungan
- **monitors**: name, group, department_id?, type, host?, port?, url?, category?, isp?, bandwidth?,
  isp_contact?, pic_user_id?, interval_seconds?, timeout_seconds?, packet_count?, failure_threshold?,
  latency_warning_ms?, expected_status_codes (json)?, keyword?, check_ssl, is_public, is_active,
  sort_order, current_status, consecutive_failures, last_checked_at?, last_status_change_at?,
  last_latency_ms?, last_packet_loss?, last_http_status?, ssl_expires_at?
- **monitor_checks**: monitor_id, checked_at, status, latency_ms?, packet_loss?, http_status?, error? ·
  index (monitor_id, checked_at)
- **monitor_incidents**: monitor_id, started_at, resolved_at?, duration_seconds?, notes?
- **monitor_daily_stats**: monitor_id, date, checks_total, checks_up, checks_degraded, checks_down,
  avg_latency_ms?, uptime_percent · unique (monitor_id, date)
- **wifi_networks**: ssid, location, department_id?, access_point?, audience, security,
  password (encrypted, text), vlan?, rotation_days?, password_changed_at?, notes?, is_active
- **wifi_password_histories**: wifi_network_id, password (encrypted), changed_by, changed_at
- **providers**: name, website?, panel_url?, support_contact?, notes?
- **hosted_services**: provider_id, name, type, identifier, account_email?, used_by?, pic_user_id?,
  last_paid_at?, expires_at, billing_cycle, cost (decimal 15,2), auto_renew, status, notes?
- **service_payments**: hosted_service_id, paid_at, period_start, period_end, amount, proof_path?,
  notes?, recorded_by
- **reminder_logs**: remindable (morphs), reminder_key, channel, sent_at · unique (remindable, key, channel)
- **tickets**: code, reporter_name, reporter_contact, department_id?, category, title, description,
  priority, status, source, assigned_to?, resolved_at?, rating?, feedback?, attachment_path?
- **ticket_comments**: ticket_id, user_id?, body, is_internal
- **announcements**: title, body, type, starts_at, ends_at?, is_public
- **exam_organizers**: name, recipient_title_default, responsible_party, bank_account_name, bank_name,
  bank_account_number, bank_account_holder, budget_name_default, guideline_year_default,
  rate_cbt_per_unit?, rate_meal_per_person_day?, is_active
- **document_templates**: key (mis. `ukom_report`), version, file_path, uploaded_by, is_active
- **ukom_reports**: booking_id?, exam_organizer_id, document_template_id, status (`draft`/`final`/`signed`),
  exam_name, period, study_program, exam_date, activity_name, letter_number, letter_sequence?, letter_year,
  letter_place, letter_date, attachment_count, subject, recipient_title, recipient_org, recipient_place,
  signer_name, signer_nip, signer_position, signer_label, signer_address_1, signer_address_2,
  responsible_role, budget_name, guideline_year, responsible_party, bank_account_name, bank_name,
  bank_account_number, bank_account_holder, total_amount (decimal 15,2), docx_path?, pdf_path?,
  signed_scan_path?, finalized_at?, created_by · unique (letter_year, letter_sequence)
- **ukom_report_items**: ukom_report_id, sort_order, description, qty_1, unit_1, qty_2?, unit_2?, note?,
  unit_price (decimal 15,2), amount (decimal 15,2), display_text
- **settings**: key (unique), value (json) — dibungkus `SettingsService` dengan cache
- Tabel paket: roles/permissions, activity_log, jobs, failed_jobs, cache, sessions

---

## 13. Pengaturan (Menu "Pengaturan")
Identitas (nama instansi, nama & NIP Kepala Unit, kontak helpdesk, jam layanan) ·
Booking (H-minimal, jam operasional, role approver, hari tampil Minggu) ·
Monitoring (lihat §5.2) · Notifikasi (channel aktif, chat ID grup Telegram, gateway WhatsApp:
URL + token, cooldown) · Pengingat (daftar hari sebelum jatuh tempo) ·
WiFi (siklus ganti password default) · Publik (interval refresh, tampilkan bar riwayat).

Token rahasia (bot Telegram, gateway WhatsApp, captcha) disimpan di `.env`, bukan di database.
Notifikasi WhatsApp dibuat sebagai custom notification channel dengan driver yang bisa diganti
(mis. gateway HTTP generik), nonaktif secara default.

---

## 14. Fase Pengerjaan (Claude Code)

Setiap fase: rencana → implementasi → test + pint → `docs/PROGRESS.md` → commit → berhenti untuk review.

**Fase 0 — Fondasi**
- Jika folder belum berisi Laravel: install Laravel ke folder sementara lalu pindahkan isinya ke root
  proyek tanpa menimpa `CLAUDE.md`, `docs/`, dan `resources/templates/` (gabungkan folder `resources/`). Inisialisasi git.
- Konfigurasi timezone/locale, MySQL, queue database, Pest.
- Install paket (§Stack), Filament panel `/admin` dengan tema TORANG.
- Inertia.js + React + TypeScript + Vite untuk halaman publik (`PublicLayout` dengan header + footer),
  token warna & font Tailwind, komponen `TorangMark.tsx` (dan `<x-torang-mark />` untuk Blade/Filament), favicon.
- Seeder role & permission + akun super admin dari `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`).
- `SettingsService` + seeder pengaturan default.
- ✅ Selesai bila: login admin berfungsi, beranda menampilkan layout kosong bergaya TORANG, test lulus.

**Fase 1 — Lab CBT & Pengajuan**
- Departemen, lab, jumlah perangkat, booking (publik + admin), validasi bentrok & kapasitas,
  approve/reject, kalender admin, notifikasi email, halaman sukses & cek status, log pemakaian.
- ✅ Test: bentrok ditolak, kapasitas ditolak, publik tidak bisa approve, kode unik, rate limit.

**Fase 2 — Jadwal Petugas**
- Staff, shift, grid jadwal mingguan, salin minggu lalu, terbitkan, Jadwal Saya, PDF, peringatan slot kosong.

**Fase 3 — Mesin Monitoring**
- Model & resource monitor, checker (ICMP/TCP/HTTP/SSL), dispatch command, job, state machine,
  insiden, notifikasi Telegram + email, agregasi harian, pruning, pengaturan interval.
- ✅ Test: checker di-mock; transisi UP→DOWN setelah N gagal; notifikasi hanya saat transisi;
  input host berbahaya (`8.8.8.8; rm -rf /`) ditolak validasi.

**Fase 4 — Beranda & Status Publik**
- Implementasi §9 lengkap dengan React sesuai desain (§11), `usePoll`, cache, endpoint JSON, halaman `/status`
  dan `/jadwal`.
- ✅ Test: halaman publik tidak memuat IP/SSID/email pemohon; hitungan ringkasan benar.

**Fase 5 — WiFi & Pengingat Hosting**
- Modul E dan F lengkap termasuk reveal password + log, riwayat, pengingat, catat pembayaran, export.

**Fase 6 — Laporan UKOM (Modul H)**
- Master kolegium, wizard laporan 5 langkah, rincian biaya dengan total live, penomoran surat otomatis,
  generate DOCX dari `resources/templates/laporan-pertanggungjawaban-ukom.docx`, konversi PDF, finalkan,
  duplikat, unggah scan, ganti template dengan validasi placeholder.
- ✅ Test: data contoh dokumen asli (2 rincian, total Rp 280.000,00) menghasilkan DOCX tanpa placeholder
  tersisa; terbilang & format rupiah benar; laporan final tidak bisa diedit; nomor urut tidak ganda.

**Fase 7 — Helpdesk, Pengumuman, Laporan, Dashboard**
- Tiket (publik + internal), pengumuman/banner, laporan CBT & SLA (Excel/PDF), widget dashboard,
  mode TV, audit log viewer, backup terjadwal.

**Fase 8 — Finalisasi**
- Seeder data contoh lengkap (§16), review keamanan & performa (N+1, index), README instalasi
  & deployment (§15), `.env.example` lengkap.

---

## 15. Non-Fungsional & Deployment

- **Keamanan:** CSRF, rate limit login, Policy di semua resource, validasi Form Request,
  sanitasi host/URL, file privat, header keamanan, session secure di produksi.
- **Performa:** eager loading, index, cache ringkasan publik, pagination; mesin monitoring tidak boleh
  memblokir request web.
- **Server produksi:** VPS Ubuntu LTS (bukan shared hosting — perlu `exec`/`ping`, queue worker,
  dan cron), Nginx + PHP-FPM 8.3 (ekstensi zip, xml, gd, intl, mbstring), MySQL/MariaDB,
  Redis (opsional), HTTPS (Certbot).
- **Node.js LTS** di VPS (atau build di CI lalu upload `public/build`) untuk `npm ci && npm run build`.
- **LibreOffice headless** (`libreoffice-writer`) + font `fonts-liberation`/`fonts-crosextra-carlito`
  (opsional `ttf-mscorefonts-installer` agar Arial/Calibri identik) untuk konversi laporan ke PDF.
- Pastikan paket `iputils-ping` terpasang dan user PHP boleh menjalankan ping.
- **Script deploy** (`deploy.sh`, dijelaskan di README): `git pull` → `composer install --no-dev -o` →
  `npm ci && npm run build` → `php artisan migrate --force` → `php artisan optimize` →
  `php artisan filament:optimize` → `php artisan queue:restart`.
- Contoh konfigurasi Nginx, Supervisor, dan cron disertakan di `deploy/`.
- **Cron:** `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
- **Supervisor:** worker `queue:work --queue=monitoring` (2–4 proses) dan `queue:work --queue=default`.
- **Jadwal scheduler:** `monitors:dispatch` (tiap menit), `bookings:mark-completed` (tiap 5 menit),
  `monitors:check-ssl` (harian 07:00), `monitors:aggregate` (00:10), `model:prune` (01:00),
  `services:send-reminders` (08:00), `bookings:send-reminders` (H-1, 16:00), `duty:send-reminders`
  (H-1, 16:00), `wifi:rotation-reminders` (Senin 08:00), `backup:run` (02:00), `backup:clean` (02:30).

---

## 16. Seeder Data Contoh
- **Departemen/unit** (sesuaikan dengan struktur resmi): Direktorat, Jurusan Keperawatan,
  Jurusan Kebidanan, Jurusan Gizi, Jurusan Kesehatan Lingkungan, Jurusan Farmasi,
  Jurusan Teknologi Laboratorium Medis, Jurusan Kesehatan Gigi, Perpustakaan, CBT Center.
- **Lab:** Lab CBT 1 (40 PC), Lab CBT 2 (40 PC), Lab CBT 3 (30 PC, 4 rusak) beserta jumlah perangkat lain.
- **Booking** minggu berjalan: campuran selesai, berlangsung (hari ini), terjadwal, maintenance, pending.
- **Shift:** Pagi 07:30–12:00, Siang 12:00–16:00 · **Petugas:** 5 orang · jadwal minggu ini terbit.
- **Monitor jaringan:** satu per departemen/unit di atas (gunakan IP dokumentasi `203.0.113.x`,
  `198.51.100.x` sebagai placeholder) + riwayat cek sintetis 7 hari (sebagian lambat/down).
- **Monitor aplikasi:** Website Poltekkes, SIAKAD, E-Learning, PMB Online, Repository, Email Kampus,
  Server Ujian CBT (URL placeholder `https://example.ac.id/...`).
- **WiFi:** 4 SSID contoh · **Provider:** Domainesia, Jagoanhosting, Hostinger ·
  **Layanan:** 6 (domain, VPS, hosting, cloud) dengan jatuh tempo bervariasi (lewat, 5 hari, 20 hari, 200 hari).
- **Tiket:** 5 contoh · **Pengumuman:** 1 maintenance terjadwal.
- **Kolegium:** Kolegium TPD (Tim Ad Hoc Kolegium TPD, Bank MANDIRI, rekening sesuai dokumen contoh,
  anggaran `UP UK Nakes Tahun 2022`, tahun pedoman `2022`, tarif sewa CBT Rp 40.000/unit,
  konsumsi Rp 50.000/orang/hari).
- **Laporan UKOM:** 1 laporan final sesuai dokumen contoh (UKOM Nasional Periode 2, D-3 Teknologi Bank Darah,
  05 September 2026, total Rp 280.000,00). Data penanda tangan diambil dari Pengaturan, jangan di-hardcode.

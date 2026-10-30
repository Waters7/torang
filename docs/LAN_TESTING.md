# Uji TORANG melalui LAN Windows

Komputer server dan perangkat penguji harus berada pada jaringan/subnet yang sama. Komputer server,
MySQL, dan proses PHP harus tetap berjalan. Gunakan alamat `http://<IP-LAN>:8001/`; panel petugas ada
di `/admin`. Alamat localhost hanya berlaku di komputer server.

## Menjalankan kembali

1. Aktifkan MySQL melalui Laragon dan gunakan konfigurasi `.env` lokal yang sudah tersedia.
2. Jalankan `npm run build`. Jangan gunakan Vite dev server untuk uji LAN ini.
3. Cari nama adapter dan IPv4 dengan `Get-NetIPConfiguration`.
4. Dari PowerShell pada direktori proyek, jalankan skrip berikut. Sesuaikan nama adapter dan lokasi PHP.

```powershell
$phpExecutable = (Get-Command php).Source
.\scripts\start-lan.ps1 -InterfaceAlias 'Wi-Fi' -PhpPath $phpExecutable -Port 8001
```

Jika PHP tidak ada di PATH, isi `$phpExecutable` dengan path lengkap `php.exe` dari Laragon.
Skrip mencetak URL dan PID, menjalankan PHP di latar belakang, serta menyimpan metadata di
`storage/framework/lan-server.json`. Log ada di `storage/logs/lan-server*.log`.
Skrip menjalankan PHP dengan `curl.cainfo`/`openssl.cafile` dari bundel CA Laragon
(`C:\laragon\etc\ssl\cacert.pem`, ganti lewat `-CaBundle`) agar verifikasi Turnstile dapat menghubungi
Cloudflare. Tanpa bundel itu, captcha gagal dengan cURL error 60 kecuali `php.ini` sudah mengatur `curl.cainfo`.

Atur `APP_URL` pada `.env` lokal ke URL LAN tersebut dan gunakan `APP_DEBUG=false`.
Jika konfigurasi dicache, jalankan `php artisan config:clear`. URL dari CLI/notifikasi mengikuti APP_URL.

## Mengizinkan firewall

Jalankan PowerShell **sebagai Administrator**, lalu:

```powershell
.\scripts\allow-lan.ps1 -InterfaceAlias 'Wi-Fi' -PhpPath $phpExecutable -Port 8001
```

Aturan `TORANG-LAN-8001` hanya mengizinkan TCP 8001 untuk PHP, IP dan adapter terpilih,
serta sumber dari subnet lokal. Menjalankan kembali skrip memperbarui aturan yang sama.
Firewall global tetap aktif dan port database tidak dibuka.

Jika alamat IP berubah setelah berganti Wi-Fi atau restart router, hentikan server LAN lama,
jalankan kedua skrip kembali, dan perbarui APP_URL. Untuk alamat tetap, gunakan reservasi DHCP
di router yang dikelola administrator jaringan.

## Pemeriksaan

- Buka URL LAN dari browser perangkat penguji, lalu coba beranda, jadwal, dan form publik.
- Panel `/admin` tetap memerlukan akun dengan hak akses yang sesuai.
- Bila server bisa membuka URL LAN tetapi perangkat lain tidak bisa, periksa apakah Wi-Fi tamu
  mengaktifkan isolasi klien atau perangkat penguji berada pada VLAN/subnet berbeda.
- Verifikasi form yang memakai captcha memerlukan koneksi ke Cloudflare dari browser dan server.

## Menghentikan akses uji

Lihat PID di `storage/framework/lan-server.json`, pastikan PID tersebut masih proses PHP server LAN
yang dicatat (PID dapat dipakai ulang setelah restart), kemudian hentikan proses melalui Task Manager
atau `Stop-Process -Id <PID>`. Untuk menutup izin firewall jalankan sebagai Administrator:

```powershell
Disable-NetFirewallRule -Name 'TORANG-LAN-8001'
```

Server LAN tidak otomatis berjalan setelah Windows dinyalakan ulang. Skrip ini untuk uji coba;
deployment VPS tetap mengikuti Fase 8.

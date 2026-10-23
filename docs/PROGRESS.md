# Progres Pengerjaan TORANG

- [x] Fase 0 — Fondasi (Laravel, Filament, Inertia + React + TS, brand, role)
- [ ] Fase 1 — Lab CBT & Pengajuan
- [ ] Fase 2 — Jadwal Petugas
- [ ] Fase 3 — Mesin Monitoring
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

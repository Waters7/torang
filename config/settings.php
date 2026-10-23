<?php

/*
|--------------------------------------------------------------------------
| Nilai default pengaturan aplikasi
|--------------------------------------------------------------------------
|
| Nilai di sini dipakai SettingsService bila key belum tersimpan di tabel
| `settings`, dan ditulis ke database oleh SettingsSeeder. Token rahasia
| (bot Telegram, gateway WhatsApp, captcha) tetap di .env, bukan di sini.
|
*/

return [

    'defaults' => [
        // Identitas
        'identity.institution_name' => 'Poltekkes Kemenkes Manado',
        'identity.unit_name' => 'UPT TIK',
        'identity.head_name' => '',
        'identity.head_nip' => '',
        'identity.helpdesk_whatsapp' => '',
        'identity.helpdesk_email' => '',
        'identity.service_hours' => 'Senin–Jumat, 07.30–16.00 WITA',

        // Booking Lab CBT
        'bookings.min_days_ahead' => 3,
        'bookings.open_time' => '07:30',
        'bookings.close_time' => '17:00',
        'bookings.approver_roles' => ['kepala_unit', 'super_admin'],
        'bookings.show_sunday' => false,

        // Monitoring (null pada monitor = pakai nilai ini)
        'monitoring.interval_minutes' => 1,
        'monitoring.timeout_seconds' => 5,
        'monitoring.packet_count' => 3,
        'monitoring.failure_threshold' => 3,
        'monitoring.latency_warning_ms' => 150,
        'monitoring.response_warning_ms' => 2000,
        'monitoring.degraded_notify_after_minutes' => null,
        'monitoring.ssl_warning_days' => [30, 14, 7],
        'monitoring.retention_days' => 30,

        // Notifikasi
        'notifications.channels' => ['telegram', 'mail'],
        'notifications.telegram_group_chat_id' => '',
        'notifications.cooldown_minutes' => 15,

        // Pengingat hosting/VPS/domain (hari sebelum jatuh tempo)
        'reminders.service_days' => [30, 14, 7, 3, 1, 0],

        // WiFi
        'wifi.rotation_days' => 90,

        // Halaman publik
        'public.refresh_seconds' => 60,
        'public.show_history_bars' => true,
        'public.uptime_good_percent' => 99.5,
        'public.uptime_warning_percent' => 95,

        // Laporan UKOM (SPTJM)
        'reports.ukom.number_pattern' => 'PP.06.03/F.XXX.20/{seq:3}/{year}',
        'reports.ukom.letter_place' => 'Manado',
        'reports.ukom.signer_name' => '',
        'reports.ukom.signer_nip' => '',
        'reports.ukom.signer_position' => 'Koordinator CBT Poltekkes Kemenkes Manado',
        'reports.ukom.signer_label' => 'Koordinator CBT',
        'reports.ukom.signer_address_1' => 'Jl. R.W. Monginsidi, Malalayang Dua, Kecamatan Malalayang, Kota Manado,',
        'reports.ukom.signer_address_2' => 'Sulawesi Utara',
        'reports.ukom.responsible_role' => 'Penanggung Jawab Lokasi TUK/Koordinator CBT',
    ],

];

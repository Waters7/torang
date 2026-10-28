<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Ticket;
use Illuminate\Database\Seeder;

class SampleHelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }
        foreach (['open', 'in_progress', 'waiting', 'resolved', 'closed'] as $i => $status) {
            Ticket::firstOrCreate(['code' => 'TIK-CONTOH-'.($i + 1)], [
                'reporter_name' => 'Pelapor Contoh '.($i + 1), 'reporter_contact' => 'pelapor'.($i + 1).'@example.test',
                'category' => ['network', 'account', 'device', 'application', 'cbt'][$i],
                'title' => ['Koneksi ruang kelas terputus', 'Permintaan pemulihan akun LMS', 'PC laboratorium perlu pemeriksaan', 'Halaman aplikasi tidak terbuka', 'Kendala ujian CBT sudah ditangani'][$i],
                'description' => 'Data sintetis untuk demonstrasi helpdesk TORANG.', 'status' => $status, 'priority' => 'normal', 'source' => 'admin',
                'resolved_at' => $i >= 3 ? now()->subHour() : null, 'closed_at' => $i === 4 ? now() : null,
            ]);
        }
        Announcement::firstOrCreate(['title' => 'Contoh maintenance terjadwal'], [
            'body' => 'Contoh informasi: pemeriksaan layanan IT dijadwalkan di luar jam layanan. Jadwal ini adalah data demonstrasi.',
            'type' => 'maintenance', 'starts_at' => now()->subHour(), 'ends_at' => now()->addDays(2), 'is_public' => true,
        ]);
    }
}

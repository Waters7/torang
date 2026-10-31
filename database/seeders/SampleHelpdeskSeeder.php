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
            $ticket = Ticket::firstOrNew(['code' => 'TIK-CONTOH-'.($i + 1)]);

            if ($ticket->exists) {
                continue;
            }

            // Waktu sintetis agar waktu tanggap & penyelesaian di Dashboard Eksekutif terisi.
            $createdAt = now()->subDays(5 - $i)->setTime(8 + $i, 15);

            $ticket->forceFill([
                'reporter_name' => 'Pelapor Contoh '.($i + 1), 'reporter_contact' => 'pelapor'.($i + 1).'@example.test',
                'category' => ['network', 'account', 'device', 'application', 'cbt'][$i],
                'title' => ['Koneksi ruang kelas terputus', 'Permintaan pemulihan akun LMS', 'PC laboratorium perlu pemeriksaan', 'Halaman aplikasi tidak terbuka', 'Kendala ujian CBT sudah ditangani'][$i],
                'description' => 'Data sintetis untuk demonstrasi helpdesk TORANG.', 'status' => $status, 'priority' => 'normal', 'source' => 'admin',
                'first_responded_at' => $status === 'open' ? null : $createdAt->copy()->addMinutes(25 + $i * 20),
                'resolved_at' => $i >= 3 ? $createdAt->copy()->addHours(3 + $i) : null,
                'closed_at' => $i === 4 ? $createdAt->copy()->addHours(9) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }
        Announcement::firstOrCreate(['title' => 'Contoh maintenance terjadwal'], [
            'body' => 'Contoh informasi: pemeriksaan layanan IT dijadwalkan di luar jam layanan. Jadwal ini adalah data demonstrasi.',
            'type' => 'maintenance', 'starts_at' => now()->subHour(), 'ends_at' => now()->addDays(2), 'is_public' => true,
        ]);
    }
}

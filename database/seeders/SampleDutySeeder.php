<?php

namespace Database\Seeders;

use App\Enums\DutyWeekStatus;
use App\Enums\Role;
use App\Models\DutyWeek;
use App\Models\Lab;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Contoh 5 petugas dan jadwal minggu berjalan yang sudah terbit. Hanya untuk lingkungan non-produksi.
 */
class SampleDutySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role(Role::SuperAdmin->value)->first();

        $staff = collect([
            ['Rivaldo Mamahit, S.Kom.', '199208142019031004', 'Pranata Komputer Ahli Pertama', '081234560101', $admin?->id],
            ['Stevani Rumondor, A.Md.Kom.', '199605232022032002', 'Pranata Komputer Terampil', '081234560102', null],
            ['Gilbert Sondakh, S.T.', '199011072018011001', 'Teknisi Jaringan', '081234560103', null],
            ['Natalia Lumenta, S.Kom.', '199412302020122003', 'Pengelola CBT', '081234560104', null],
            ['Fandy Tumbelaka', null, 'Tenaga Teknis IT (PPNPN)', '081234560105', null],
        ])->map(fn (array $row): Staff => Staff::updateOrCreate(['name' => $row[0]], [
            'nip' => $row[1],
            'position' => $row[2],
            'phone' => $row[3],
            'user_id' => $row[4],
            'is_active' => true,
        ]));

        $morning = Shift::query()->where('name', 'Pagi')->firstOrFail();
        $afternoon = Shift::query()->where('name', 'Siang')->firstOrFail();
        $lab3 = Lab::query()->where('code', 'LAB-3')->first();
        $monday = CarbonImmutable::today()->startOfWeek();

        $week = DutyWeek::updateOrCreate(['week_start' => $monday->toDateString()], [
            'status' => DutyWeekStatus::Published,
            'published_at' => now(),
            'created_by' => $admin?->id ?? User::query()->value('id'),
            'notes' => 'Contoh jadwal minggu berjalan.',
        ]);

        $week->assignments()->delete();

        foreach ($week->days() as $index => $day) {
            // Rotasi sederhana: dua petugas pagi, satu petugas siang untuk seluruh CBT Center.
            $rows = [
                [$morning, null, $staff[$index % 5]],
                [$morning, null, $staff[($index + 1) % 5]],
                [$afternoon, null, $staff[($index + 2) % 5]],
            ];

            // Lab CBT 3 punya petugas khusus pada hari Rabu.
            if ($lab3 && $index === 2) {
                $rows[] = [$morning, $lab3->id, $staff[3]];
            }

            foreach ($rows as [$shift, $labId, $member]) {
                $week->assignments()->firstOrCreate([
                    'date' => $day->toDateString(),
                    'shift_id' => $shift->id,
                    'lab_id' => $labId,
                    'staff_id' => $member->id,
                ]);
            }
        }
    }
}

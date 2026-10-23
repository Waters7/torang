<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Lab;
use App\Models\User;
use App\Services\Bookings\BookingCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Contoh pengajuan di sekitar minggu berjalan: selesai, berlangsung, terjadwal, maintenance,
 * menunggu, dan ditolak. Hanya untuk lingkungan non-produksi.
 */
class SampleBookingSeeder extends Seeder
{
    public function run(BookingCodeGenerator $codes): void
    {
        $labs = Lab::query()->pluck('id', 'code');
        $departments = Department::query()->pluck('id', 'short_name');
        $admin = User::role(Role::SuperAdmin->value)->first();
        $today = CarbonImmutable::today();

        // Kegiatan "berlangsung": satu jam lalu sampai dua jam lagi, tetap di hari yang sama.
        $now = CarbonImmutable::now();
        $ongoingStart = $now->subHour()->startOfHour()->max($today);
        $ongoingEnd = $now->addHours(2)->startOfHour()->min($today->setTime(23, 59));

        $samples = [
            ['LAB-1', 'Gizi', 'Ujian Akhir Semester Gizi', ActivityType::Exam, $today->subDay(), '08:00', '10:00', 36, BookingStatus::Completed],
            ['LAB-2', 'Keperawatan', 'Try Out UKOM Keperawatan', ActivityType::UkomTryout, $today, $ongoingStart->format('H:i'), $ongoingEnd->format('H:i'), 38, BookingStatus::Approved],
            ['LAB-3', 'CBT Center', 'Pembaruan aplikasi ujian & cek PC', ActivityType::Maintenance, $today->addDay(), '08:00', '12:00', 0, BookingStatus::Approved],
            ['LAB-1', 'Kebidanan', 'Praktikum Sistem Informasi Kebidanan', ActivityType::Practicum, $today->addDays(2), '09:00', '12:00', 32, BookingStatus::Approved],
            ['LAB-2', 'Farmasi', 'Pelatihan Aplikasi Farmasi Klinik', ActivityType::Training, $today->addDays(3), '13:00', '15:30', 25, BookingStatus::Rejected],
            ['LAB-1', 'TLM', 'Ujian Tengah Semester TLM', ActivityType::Exam, $today->addDays(4), '08:00', '10:00', 40, BookingStatus::Approved],
            ['LAB-2', 'Kesling', 'Tes Seleksi Asisten Laboratorium', ActivityType::Recruitment, $today->addDays(5), '08:00', '11:00', 30, BookingStatus::Pending],
            ['LAB-3', 'Kesgi', 'Try Out UKOM Kesehatan Gigi', ActivityType::UkomTryout, $today->addDays(6), '13:00', '16:00', 24, BookingStatus::Pending],
        ];

        $applicants = [
            ['Ns. Maria Wenas, S.Kep., M.Kep.', '198705122010122001', 'maria.wenas@example.ac.id', '081234560001'],
            ['Dr. Yohanes Lumowa, S.Gz., M.Kes.', '198110032005011002', 'yohanes.lumowa@example.ac.id', '081234560002'],
            ['Grace Pangemanan, A.Md.Keb.', '0912048801', 'grace.p@example.ac.id', '081234560003'],
        ];

        foreach ($samples as $index => [$lab, $department, $activity, $type, $date, $start, $end, $participants, $status]) {
            [$name, $identity, $email, $phone] = $applicants[$index % count($applicants)];
            $isAdmin = $type === ActivityType::Maintenance;

            $booking = Booking::create([
                'code' => $codes->generate(),
                'lab_id' => $labs[$lab],
                'department_id' => $departments[$department] ?? null,
                'applicant_name' => $isAdmin ? ($admin?->name ?? 'Petugas UPT TIK') : $name,
                'applicant_identity_number' => $identity,
                'applicant_email' => $isAdmin ? ($admin?->email ?? 'uptk@example.ac.id') : $email,
                'applicant_phone' => $phone,
                'activity_name' => $activity,
                'activity_type' => $type,
                'date' => $date->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'participants_count' => $participants,
                'status' => $status,
                'source' => $isAdmin ? BookingSource::Admin : BookingSource::Public,
                'approved_by' => in_array($status, [BookingStatus::Approved, BookingStatus::Completed, BookingStatus::Rejected], true) ? $admin?->id : null,
                'approved_at' => $status === BookingStatus::Pending ? null : now(),
                'rejection_reason' => $status === BookingStatus::Rejected ? 'Lab dipakai untuk ujian terjadwal pada jam tersebut. Silakan pilih Lab CBT 3.' : null,
            ]);

            if ($status === BookingStatus::Completed && $admin) {
                $booking->usageLog()->create([
                    'attendees_count' => $participants - 2,
                    'devices_used' => $participants,
                    'devices_problem' => 1,
                    'notes' => 'Satu PC tidak bisa login ke aplikasi ujian, peserta dipindah ke PC cadangan.',
                    'filled_by' => $admin->id,
                ]);
            }
        }
    }
}

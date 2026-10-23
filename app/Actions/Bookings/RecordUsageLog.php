<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Isi/ubah log pemakaian setelah kegiatan; booking yang disetujui otomatis ditandai selesai.
 */
class RecordUsageLog
{
    /**
     * @param  array{attendees_count: int, devices_used: int, devices_problem: int, notes?: string|null}  $data
     */
    public function handle(Booking $booking, array $data, User $filler): UsageLog
    {
        if (! in_array($booking->status, [BookingStatus::Approved, BookingStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'booking_id' => 'Log pemakaian hanya untuk pengajuan yang disetujui atau selesai.',
            ]);
        }

        if (! $booking->hasStarted()) {
            throw ValidationException::withMessages([
                'booking_id' => 'Log pemakaian baru bisa diisi setelah kegiatan dimulai.',
            ]);
        }

        if ($data['devices_problem'] > $data['devices_used']) {
            throw ValidationException::withMessages([
                'devices_problem' => 'Perangkat bermasalah tidak boleh melebihi perangkat terpakai.',
            ]);
        }

        return DB::transaction(function () use ($booking, $data, $filler): UsageLog {
            $log = UsageLog::updateOrCreate(
                ['booking_id' => $booking->id],
                [...$data, 'filled_by' => $filler->id],
            );

            if ($booking->status === BookingStatus::Approved) {
                $booking->update(['status' => BookingStatus::Completed]);
            }

            return $log;
        });
    }
}

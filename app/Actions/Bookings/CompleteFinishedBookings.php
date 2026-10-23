<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * Tandai selesai semua booking disetujui yang jam selesainya sudah lewat.
 */
class CompleteFinishedBookings
{
    public function handle(): int
    {
        $now = now();
        $completed = 0;

        Booking::query()
            ->where('status', BookingStatus::Approved)
            ->whereDate('date', '<=', $now->toDateString())
            ->orderBy('id')
            ->each(function (Booking $booking) use ($now, &$completed): void {
                if ($booking->endsAt()->lessThanOrEqualTo($now)) {
                    $booking->update(['status' => BookingStatus::Completed]);
                    $completed++;
                }
            });

        return $completed;
    }
}

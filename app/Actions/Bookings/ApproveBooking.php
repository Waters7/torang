<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\BookingTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Bookings\BookingNotifier;
use App\Services\Bookings\BookingRules;
use Illuminate\Support\Facades\DB;

class ApproveBooking
{
    public function __construct(
        private readonly BookingRules $rules,
        private readonly BookingNotifier $notifier,
    ) {}

    public function handle(Booking $booking, User $approver): Booking
    {
        if (! $booking->status->canTransitionTo(BookingStatus::Approved)) {
            throw BookingTransitionException::make($booking, BookingStatus::Approved);
        }

        DB::transaction(function () use ($booking, $approver): void {
            // Cek ulang: lab bisa saja masuk maintenance setelah pengajuan dibuat.
            $this->rules->validate([
                'lab_id' => $booking->lab_id,
                'date' => $booking->date->toDateString(),
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'participants_count' => $booking->participants_count,
                'activity_type' => $booking->activity_type,
            ], isPublic: false, ignore: $booking);

            $booking->update([
                'status' => BookingStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);
        });

        $this->notifier->approved($booking);

        return $booking;
    }
}

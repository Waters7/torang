<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\BookingTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Bookings\BookingNotifier;
use Illuminate\Validation\ValidationException;

class RejectBooking
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    public function handle(Booking $booking, User $approver, string $reason): Booking
    {
        if (! $booking->status->canTransitionTo(BookingStatus::Rejected)) {
            throw BookingTransitionException::make($booking, BookingStatus::Rejected);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['rejection_reason' => 'Alasan penolakan wajib diisi.']);
        }

        $booking->update([
            'status' => BookingStatus::Rejected,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => trim($reason),
        ]);

        $this->notifier->rejected($booking);

        return $booking;
    }
}

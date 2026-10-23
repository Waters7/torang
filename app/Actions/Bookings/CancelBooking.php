<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\BookingTransitionException;
use App\Models\Booking;

class CancelBooking
{
    public function handle(Booking $booking): Booking
    {
        if (! $booking->status->canTransitionTo(BookingStatus::Cancelled)) {
            throw BookingTransitionException::make($booking, BookingStatus::Cancelled);
        }

        $booking->update(['status' => BookingStatus::Cancelled]);

        return $booking;
    }
}

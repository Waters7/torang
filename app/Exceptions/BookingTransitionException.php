<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use App\Models\Booking;
use DomainException;

class BookingTransitionException extends DomainException
{
    public static function make(Booking $booking, BookingStatus $to): self
    {
        return new self("Pengajuan {$booking->code} berstatus {$booking->status->label()} dan tidak bisa diubah menjadi {$to->label()}.");
    }
}

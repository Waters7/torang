<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\Lab;
use App\Services\Bookings\BookingRules;
use Illuminate\Support\Facades\DB;

class UpdateBooking
{
    public function __construct(private readonly BookingRules $rules) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data): Booking {
            Lab::query()->whereKey($data['lab_id'] ?? $booking->lab_id)->lockForUpdate()->first();

            $this->rules->validate([
                'lab_id' => $data['lab_id'] ?? $booking->lab_id,
                'date' => $data['date'] ?? $booking->date->toDateString(),
                'start_time' => $data['start_time'] ?? $booking->start_time,
                'end_time' => $data['end_time'] ?? $booking->end_time,
                'participants_count' => $data['participants_count'] ?? $booking->participants_count,
                'activity_type' => $data['activity_type'] ?? $booking->activity_type,
            ], isPublic: false, ignore: $booking);

            $booking->update($data);

            return $booking;
        });
    }
}

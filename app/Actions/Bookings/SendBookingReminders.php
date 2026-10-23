<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Bookings\BookingNotifier;

/**
 * Pengingat H-1 ke pemohon untuk booking disetujui besok. Tercatat di reminder_logs agar tidak ganda.
 */
class SendBookingReminders
{
    public const KEY = 'h-1';

    public function __construct(private readonly BookingNotifier $notifier) {}

    public function handle(): int
    {
        $sent = 0;

        Booking::query()
            ->where('status', BookingStatus::Approved)
            ->whereDate('date', now()->addDay()->toDateString())
            ->whereDoesntHave('reminderLogs', fn ($query) => $query->where('reminder_key', self::KEY)->where('channel', 'mail'))
            ->orderBy('id')
            ->each(function (Booking $booking) use (&$sent): void {
                $this->notifier->reminder($booking);

                $booking->reminderLogs()->create([
                    'reminder_key' => self::KEY,
                    'channel' => 'mail',
                    'sent_at' => now(),
                ]);

                $sent++;
            });

        return $sent;
    }
}

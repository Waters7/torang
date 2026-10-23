<?php

namespace App\Notifications\Bookings;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

/**
 * Pemberitahuan internal ke grup Telegram UPT TIK bahwa ada pengajuan baru.
 */
class NewBookingSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $booking = $this->booking->loadMissing(['lab', 'department']);

        return TelegramMessage::create()
            ->line('*Pengajuan Lab CBT baru*')
            ->escapedLine("{$booking->code} · {$booking->lab->name}")
            ->escapedLine("Kegiatan: {$booking->activity_name} ({$booking->activity_type->label()})")
            ->escapedLine('Penyelenggara: '.($booking->department?->name ?? '-'))
            ->escapedLine('Jadwal: '.Format::dayDate($booking->date).', '.Format::timeRange($booking->start_time, $booking->end_time))
            ->escapedLine("Peserta: {$booking->participants_count} orang")
            ->escapedLine('Tinjau: '.BookingResource::getUrl('view', ['record' => $booking], panel: 'admin'));
    }
}

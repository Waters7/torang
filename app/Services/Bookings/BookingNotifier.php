<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Notifications\Bookings\BookingApproved;
use App\Notifications\Bookings\BookingReceived;
use App\Notifications\Bookings\BookingRejected;
use App\Notifications\Bookings\BookingReminder;
use App\Notifications\Bookings\NewBookingSubmitted;
use App\Services\SettingsService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Menentukan tujuan notifikasi pengajuan: email pemohon dan grup Telegram internal.
 * Pemohon tidak punya akun, jadi dikirim lewat on-demand notification.
 */
class BookingNotifier
{
    public function __construct(private readonly SettingsService $settings) {}

    public function submitted(Booking $booking): void
    {
        $this->applicant($booking)->notify(new BookingReceived($booking));

        if ($group = $this->telegramGroup()) {
            $group->notify(new NewBookingSubmitted($booking));
        }
    }

    public function approved(Booking $booking): void
    {
        $this->applicant($booking)->notify(new BookingApproved($booking));
    }

    public function rejected(Booking $booking): void
    {
        $this->applicant($booking)->notify(new BookingRejected($booking));
    }

    public function reminder(Booking $booking): void
    {
        $this->applicant($booking)->notify(new BookingReminder($booking));
    }

    private function applicant(Booking $booking): AnonymousNotifiable
    {
        return Notification::route('mail', [$booking->applicant_email => $booking->applicant_name]);
    }

    /**
     * Grup Telegram hanya dipakai bila channel aktif dan token bot serta chat ID sudah diisi.
     */
    private function telegramGroup(): ?AnonymousNotifiable
    {
        $chatId = $this->settings->get('notifications.telegram_group_chat_id');

        if (blank($chatId)
            || blank(config('services.telegram-bot-api.token'))
            || ! in_array('telegram', (array) $this->settings->get('notifications.channels', []), true)) {
            return null;
        }

        return Notification::route('telegram', $chatId);
    }
}

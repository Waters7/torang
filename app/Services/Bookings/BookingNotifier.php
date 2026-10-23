<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Notifications\Bookings\BookingApproved;
use App\Notifications\Bookings\BookingReceived;
use App\Notifications\Bookings\BookingRejected;
use App\Notifications\Bookings\BookingReminder;
use App\Notifications\Bookings\NewBookingSubmitted;
use App\Services\Notifications\NotificationChannels;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Menentukan tujuan notifikasi pengajuan: email (+ WhatsApp bila aktif) pemohon dan grup Telegram internal.
 * Pemohon tidak punya akun, jadi dikirim lewat on-demand notification.
 */
class BookingNotifier
{
    public function __construct(private readonly NotificationChannels $channels) {}

    public function submitted(Booking $booking): void
    {
        $this->applicant($booking)->notify(new BookingReceived($booking));

        $this->channels->telegramGroup()?->notify(new NewBookingSubmitted($booking));
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

    /**
     * Email pemohon selalu dikirim; WhatsApp hanya bila kanalnya aktif.
     */
    private function applicant(Booking $booking): AnonymousNotifiable
    {
        $notifiable = Notification::route('mail', [$booking->applicant_email => $booking->applicant_name]);

        if (filled($booking->applicant_phone) && $this->channels->enabled(NotificationChannels::WHATSAPP)) {
            $notifiable->route(NotificationChannels::WHATSAPP, $booking->applicant_phone);
        }

        return $notifiable;
    }
}

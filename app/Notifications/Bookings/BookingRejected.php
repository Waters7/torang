<?php

namespace App\Notifications\Bookings;

use App\Models\Booking;
use App\Notifications\Concerns\ViaOnDemandRoutes;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRejected extends Notification implements ShouldQueue
{
    use Queueable, ViaOnDemandRoutes;

    public function __construct(public Booking $booking) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('lab');

        return (new MailMessage)
            ->subject("Pengajuan Lab CBT ditolak — {$booking->code}")
            ->greeting("Halo {$booking->applicant_name},")
            ->line("Mohon maaf, pengajuan pemakaian {$booking->lab->name} untuk kegiatan \"{$booking->activity_name}\" pada ".Format::dayDate($booking->date).' **ditolak**.')
            ->line("Alasan: {$booking->rejection_reason}")
            ->line('Silakan ajukan kembali dengan jadwal atau lab lain.')
            ->action('Ajukan kembali', route('bookings.create'))
            ->salutation('Salam, UPT TIK');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $booking = $this->booking->loadMissing('lab');

        return "Mohon maaf, pengajuan {$booking->code} ({$booking->lab->name}, ".Format::dayDate($booking->date)
            .") DITOLAK.\nAlasan: {$booking->rejection_reason}\nAjukan kembali: ".route('bookings.create')."\n— UPT TIK";
    }
}

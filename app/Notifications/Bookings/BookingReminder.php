<?php

namespace App\Notifications\Bookings;

use App\Models\Booking;
use App\Notifications\Concerns\ViaOnDemandRoutes;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminder extends Notification implements ShouldQueue
{
    use Queueable, ViaOnDemandRoutes;

    public function __construct(public Booking $booking) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('lab');

        return (new MailMessage)
            ->subject("Pengingat: kegiatan di {$booking->lab->name} besok — {$booking->code}")
            ->greeting("Halo {$booking->applicant_name},")
            ->line("Kegiatan \"{$booking->activity_name}\" di {$booking->lab->name} dijadwalkan besok.")
            ->line('Jadwal: '.Format::dayDate($booking->date).', '.Format::timeRange($booking->start_time, $booking->end_time).'.')
            ->line('Mohon hadir 30 menit sebelum kegiatan. Hubungi helpdesk UPT TIK bila ada perubahan.')
            ->salutation('Salam, UPT TIK');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $booking = $this->booking->loadMissing('lab');

        return "Pengingat: \"{$booking->activity_name}\" di {$booking->lab->name} besok, "
            .Format::dayDate($booking->date).', '.Format::timeRange($booking->start_time, $booking->end_time)
            .".\nKode: {$booking->code}\n— UPT TIK";
    }
}

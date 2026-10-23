<?php

namespace App\Notifications\Bookings;

use App\Models\Booking;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('lab');

        return (new MailMessage)
            ->subject("Pengajuan Lab CBT disetujui — {$booking->code}")
            ->greeting("Halo {$booking->applicant_name},")
            ->line("Pengajuan pemakaian {$booking->lab->name} untuk kegiatan \"{$booking->activity_name}\" **disetujui**.")
            ->line('Jadwal: '.Format::dayDate($booking->date).', '.Format::timeRange($booking->start_time, $booking->end_time).'.')
            ->line('Petugas IT akan mendampingi sesuai jadwal jaga. Mohon hadir 30 menit sebelum kegiatan untuk persiapan.')
            ->action('Lihat status pengajuan', route('bookings.status', ['kode' => $booking->code]))
            ->salutation('Salam, UPT TIK');
    }
}

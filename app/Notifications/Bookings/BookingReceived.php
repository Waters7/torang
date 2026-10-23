<?php

namespace App\Notifications\Bookings;

use App\Models\Booking;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReceived extends Notification implements ShouldQueue
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
            ->subject("Pengajuan Lab CBT diterima — {$booking->code}")
            ->greeting("Halo {$booking->applicant_name},")
            ->line("Pengajuan pemakaian {$booking->lab->name} untuk kegiatan \"{$booking->activity_name}\" sudah kami terima.")
            ->line('Jadwal: '.Format::dayDate($booking->date).', '.Format::timeRange($booking->start_time, $booking->end_time).'.')
            ->line("Kode pengajuan: **{$booking->code}**. Simpan kode ini untuk mengecek status.")
            ->line('Status saat ini: menunggu persetujuan Kepala UPT TIK.')
            ->action('Cek status pengajuan', route('bookings.status', ['kode' => $booking->code]))
            ->salutation('Salam, UPT TIK');
    }
}

<?php

namespace App\Notifications\Helpdesk;

use App\Models\Ticket;
use App\Notifications\Concerns\ViaOnDemandRoutes;
use App\Services\Helpdesk\ReporterUpdates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabar tiket ke pelapor (diproses/selesai). Teks sudah disusun ReporterUpdates — bisa disunting petugas —
 * dan dipakai apa adanya untuk WhatsApp maupun email.
 */
class ReporterTicketUpdate extends Notification implements ShouldQueue
{
    use Queueable, ViaOnDemandRoutes;

    public function __construct(
        public Ticket $ticket,
        public string $kind,
        public string $text,
    ) {}

    public function toWhatsApp(object $notifiable): string
    {
        return $this->text;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(match ($this->kind) {
            ReporterUpdates::RESOLVED => "[TORANG] Tiket {$this->ticket->code} selesai dikerjakan",
            default => "[TORANG] Tiket {$this->ticket->code} sedang diproses",
        });

        // Teks WhatsApp memakai *tebal*; di email ditampilkan polos per baris, baris pertama ("Halo …") jadi sapaan.
        $lines = array_values(array_filter(preg_split('/\R/', str_replace('*', '', $this->text)), fn (string $line): bool => trim($line) !== ''));
        $mail->greeting(array_shift($lines) ?? 'Halo,');

        foreach ($lines as $line) {
            $mail->line($line);
        }

        if ($this->kind === ReporterUpdates::RESOLVED) {
            $mail->action('Beri penilaian layanan', app(ReporterUpdates::class)->ratingUrl($this->ticket));
        }

        return $mail->salutation(' ');
    }
}

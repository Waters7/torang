<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Helpdesk\ReporterTicketUpdate;
use App\Services\Notifications\NotificationChannels;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Kabar tiket ke pelapor: "sedang diproses" dan "selesai" beserta pekerjaan yang dilakukan.
 * Otomatis saat status berubah (email bila kontaknya email; WhatsApp bila nomor HP dan kanal aktif), atau manual
 * oleh petugas (chat WhatsApp lewat wa.me / balas email). Setiap kabar tercatat di audit log tiket.
 */
class ReporterUpdates
{
    public const PROGRESS = 'progress';

    public const RESOLVED = 'resolved';

    public const LABELS = [
        self::PROGRESS => 'Tiket diproses',
        self::RESOLVED => 'Tiket selesai',
    ];

    public const EVENT = 'reporter_notified';

    /** Masa berlaku tautan penilaian bertanda tangan di pesan "selesai". */
    public const RATING_LINK_DAYS = 14;

    public function __construct(
        private readonly NotificationChannels $channels,
        private readonly SettingsService $settings,
    ) {}

    public function kindFor(Ticket $ticket): string
    {
        return $ticket->status->isFinished() ? self::RESOLVED : self::PROGRESS;
    }

    /**
     * Teks pesan untuk WhatsApp/email (WhatsApp memakai *tebal*).
     */
    public function message(Ticket $ticket, string $kind): string
    {
        $ticket->loadMissing('assignee');
        $staff = $ticket->assignee?->name;
        $signature = '— '.$this->settings->get('identity.unit_name').' '.$this->settings->get('identity.institution_name');
        $opening = "Halo {$ticket->reporter_name},\n\nLaporan gangguan Anda *{$ticket->code}* — \"{$ticket->title}\"";

        if ($kind === self::RESOLVED) {
            $finishedAt = $ticket->resolved_at ? ' pada '.$ticket->resolved_at->translatedFormat('j F Y H:i').' WITA' : '';

            return "{$opening} sudah *SELESAI* dikerjakan{$finishedAt}.\n\n"
                ."*Pekerjaan yang dilakukan:*\n".($ticket->resolution_notes ?: '-')."\n\n"
                .($staff ? "Petugas: {$staff}\n" : '')
                .'Mohon beri penilaian layanan kami (berlaku '.self::RATING_LINK_DAYS." hari):\n{$this->ratingUrl($ticket)}\n\n"
                .$signature;
        }

        return "{$opening} sedang *DIPROSES*".($staff ? " oleh {$staff}" : '').".\n"
            ."Kami akan mengabari kembali setelah selesai dikerjakan.\n\n"
            ."Cek status: {$this->statusUrl($ticket)}\n\n"
            .$signature;
    }

    /**
     * Tautan wa.me berisi pesan siap kirim, atau null bila kontak pelapor bukan nomor HP.
     */
    public function whatsappUrl(Ticket $ticket, string $text): ?string
    {
        $phone = $ticket->reporterPhone();

        return $phone ? "https://wa.me/{$phone}?text=".rawurlencode($text) : null;
    }

    /**
     * Penilaian tanpa token tersimpan: URL bertanda tangan yang kedaluwarsa otomatis.
     */
    public function ratingUrl(Ticket $ticket): string
    {
        return URL::temporarySignedRoute('tickets.review', now()->addDays(self::RATING_LINK_DAYS), ['code' => $ticket->code]);
    }

    public function statusUrl(Ticket $ticket): string
    {
        return route('tickets.status', ['kode' => $ticket->code]);
    }

    /**
     * Kabar otomatis setelah status berubah ke Dikerjakan atau Selesai.
     */
    public function notifyStatusChange(Ticket $ticket): void
    {
        $kind = $ticket->status === TicketStatus::Resolved ? self::RESOLVED : self::PROGRESS;
        $text = $this->message($ticket, $kind);

        if ($email = $ticket->reporterEmail()) {
            $this->sendMail($ticket, $email, $kind, $text, null);
        }

        if (($phone = $ticket->reporterPhone()) && $this->channels->enabled(NotificationChannels::WHATSAPP)) {
            Notification::route(NotificationChannels::WHATSAPP, $phone)->notify(new ReporterTicketUpdate($ticket, $kind, $text));
            $this->record($ticket, $kind, 'WhatsApp (gateway)', null);
        }
    }

    /**
     * Balas pelapor lewat email dengan pesan yang sudah disunting petugas.
     */
    public function replyByEmail(Ticket $ticket, string $text, User $actor): void
    {
        $email = $ticket->reporterEmail();
        abort_unless($email !== null && $actor->can('update', $ticket), 403);

        $this->sendMail($ticket, $email, $this->kindFor($ticket), $text, $actor);
    }

    /**
     * Petugas membuka chat WhatsApp (wa.me); pengiriman terjadi di aplikasi WhatsApp petugas.
     */
    public function openWhatsApp(Ticket $ticket, string $text, User $actor): string
    {
        $url = $this->whatsappUrl($ticket, $text);
        abort_unless($url !== null && $actor->can('update', $ticket), 403);

        $this->record($ticket, $this->kindFor($ticket), 'chat WhatsApp', $actor);

        return $url;
    }

    private function sendMail(Ticket $ticket, string $email, string $kind, string $text, ?User $actor): void
    {
        Notification::route('mail', [$email => $ticket->reporter_name])->notify(new ReporterTicketUpdate($ticket, $kind, $text));

        $this->record($ticket, $kind, 'email', $actor);
    }

    private function record(Ticket $ticket, string $kind, string $via, ?User $actor): void
    {
        activity('tickets')
            ->performedOn($ticket)
            ->causedBy($actor)
            ->event(self::EVENT)
            ->withProperties(['kind' => $kind, 'via' => $via, 'mode' => $actor ? 'manual' : 'otomatis'])
            ->log('Kabar ke pelapor: '.self::LABELS[$kind]." ({$via})");
    }
}

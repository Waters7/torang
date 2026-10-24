<?php

namespace App\Notifications\Hosting;

use App\Filament\Resources\HostedServices\HostedServiceResource;
use App\Models\HostedService;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

/**
 * Pengingat jatuh tempo layanan hosting/VPS/domain (H-30 … H-0). Tanpa email akun maupun kredensial.
 */
class HostedServiceExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>|null  $channels  Null = semua rute (on-demand) atau email (pengguna).
     */
    public function __construct(
        public HostedService $service,
        public int $daysLeft,
        public ?array $channels = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels ?? ($notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail']);
    }

    public function title(): string
    {
        $subject = "{$this->service->type->label()} {$this->service->identifier}";

        return match (true) {
            $this->daysLeft > 0 => "{$subject} berakhir {$this->daysLeft} hari lagi",
            $this->daysLeft === 0 => "{$subject} berakhir hari ini",
            default => "{$subject} sudah kedaluwarsa ".abs($this->daysLeft).' hari',
        };
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $service = $this->service->loadMissing(['provider', 'pic']);

        return array_values(array_filter([
            "Layanan: {$service->name}",
            "Provider: {$service->provider->name}",
            'Berlaku sampai: '.Format::date($service->expires_at),
            'Biaya: '.Format::rupiah($service->cost).' / '.mb_strtolower($service->billing_cycle->label()),
            $service->auto_renew
                ? 'Perpanjangan otomatis: pastikan saldo/kartu pembayaran di provider mencukupi.'
                : 'Perpanjangan manual: segera bayar lalu catat pembayaran di TORANG.',
            $service->used_by ? "Dipakai oleh: {$service->used_by}" : null,
            $service->pic ? "PIC: {$service->pic->name}" : null,
            $service->provider->panel_url ? "Panel: {$service->provider->panel_url}" : null,
        ]));
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()->line('*'.TelegramMessage::escapeLegacyMarkdown($this->title()).'*');

        foreach ($this->lines() as $line) {
            $message->escapedLine($line);
        }

        return $message;
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->title()."\n".implode("\n", $this->lines());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("[TORANG] {$this->title()}");

        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail
            ->action('Buka di TORANG', HostedServiceResource::getUrl('view', ['record' => $this->service], panel: 'admin'))
            ->salutation('TORANG · UPT TIK');
    }
}

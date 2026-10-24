<?php

namespace App\Notifications\Wifi;

use App\Filament\Resources\WifiNetworks\WifiNetworkResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

/**
 * Ringkasan mingguan SSID yang perlu ganti password. Hanya SSID, lokasi, dan jadwal — tanpa password.
 */
class WifiRotationDue extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $items  Baris "SSID (lokasi) — keterangan jadwal".
     */
    public function __construct(public array $items) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }

    public function title(): string
    {
        return count($this->items).' password WiFi perlu diganti';
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()->line('*'.TelegramMessage::escapeLegacyMarkdown($this->title()).'*');

        foreach ($this->items as $item) {
            $message->escapedLine("• {$item}");
        }

        return $message->escapedLine('Ganti password di perangkat lalu perbarui di TORANG › Infrastruktur › WiFi.');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[TORANG] {$this->title()}")
            ->line('Jaringan berikut sudah atau segera melewati jadwal ganti password:');

        foreach ($this->items as $item) {
            $mail->line("• {$item}");
        }

        return $mail
            ->line('Ganti password di perangkat, lalu perbarui di TORANG agar password lama tersimpan di riwayat.')
            ->action('Buka daftar WiFi', WifiNetworkResource::getUrl(panel: 'admin'))
            ->salutation('TORANG · UPT TIK');
    }
}

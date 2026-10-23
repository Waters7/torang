<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Kanal notifikasi WhatsApp lewat gateway HTTP generik (SPEC §13).
 *
 * Driver "http": POST JSON {"target": "62812…", "message": "…"} ke WHATSAPP_GATEWAY_URL dengan
 * header Authorization berisi WHATSAPP_GATEWAY_TOKEN. Driver "log": hanya ditulis ke log (lokal).
 * Notifikasi perlu method toWhatsApp() yang mengembalikan teks pesan.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (blank($phone) || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $target = self::normalizePhone((string) $phone);
        $message = (string) $notification->toWhatsApp($notifiable);

        if (config('services.whatsapp.driver') === 'log') {
            Log::info('WhatsApp (log driver)', ['target' => $target, 'message' => $message]);

            return;
        }

        $url = config('services.whatsapp.url');
        $token = config('services.whatsapp.token');

        if (blank($url) || blank($token)) {
            throw new RuntimeException('Gateway WhatsApp belum dikonfigurasi (WHATSAPP_GATEWAY_URL / WHATSAPP_GATEWAY_TOKEN).');
        }

        Http::withHeaders(['Authorization' => $token])
            ->acceptJson()
            ->timeout(15)
            ->post($url, ['target' => $target, 'message' => $message])
            ->throw();
    }

    /** "0812-3456-7890" / "+62 812…" → "6281234567890" */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}

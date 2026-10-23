<?php

namespace App\Services\Notifications;

use App\Services\SettingsService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Status kanal notifikasi: aktif di Pengaturan (notifications.channels) DAN kredensialnya ada di .env.
 */
class NotificationChannels
{
    public const TELEGRAM = 'telegram';

    public const MAIL = 'mail';

    public const WHATSAPP = 'whatsapp';

    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(string $channel): bool
    {
        if (! in_array($channel, (array) $this->settings->get('notifications.channels', []), true)) {
            return false;
        }

        return match ($channel) {
            self::TELEGRAM => filled(config('services.telegram-bot-api.token')),
            self::WHATSAPP => config('services.whatsapp.driver') === 'log'
                || (filled(config('services.whatsapp.url')) && filled(config('services.whatsapp.token'))),
            default => true,
        };
    }

    /**
     * Grup Telegram internal UPT TIK, bila kanal aktif dan chat ID diisi.
     */
    public function telegramGroup(): ?AnonymousNotifiable
    {
        $chatId = $this->settings->get('notifications.telegram_group_chat_id');

        if (blank($chatId) || ! $this->enabled(self::TELEGRAM)) {
            return null;
        }

        return Notification::route(self::TELEGRAM, $chatId);
    }

    /**
     * Kanal untuk orang dengan Telegram chat ID dan/atau nomor WhatsApp (mis. petugas jaga).
     *
     * @return list<string>
     */
    public function personalChannels(?string $telegramChatId, ?string $phone): array
    {
        return array_values(array_filter([
            filled($telegramChatId) && $this->enabled(self::TELEGRAM) ? self::TELEGRAM : null,
            filled($phone) && $this->enabled(self::WHATSAPP) ? self::WHATSAPP : null,
        ]));
    }
}

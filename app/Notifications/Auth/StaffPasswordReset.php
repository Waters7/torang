<?php

namespace App\Notifications\Auth;

use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email tautan atur ulang kata sandi akun petugas. Tautan bertanda tangan dan berlaku sesuai
 * `auth.passwords.users.expire` (bawaan 60 menit).
 */
class StaffPasswordReset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = app(SettingsService::class);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('[TORANG] Atur ulang kata sandi')
            ->greeting("Halo, {$notifiable->name}")
            ->line('Kami menerima permintaan atur ulang kata sandi untuk akun petugas TORANG Anda.')
            ->action('Atur ulang kata sandi', $this->url)
            ->line("Tautan ini berlaku {$minutes} menit dan hanya bisa dipakai sekali.")
            ->line('Bila Anda tidak memintanya, abaikan email ini — kata sandi Anda tidak berubah.')
            ->salutation('Salam, '.$settings->get('identity.unit_name').' '.$settings->get('identity.institution_name'));
    }
}

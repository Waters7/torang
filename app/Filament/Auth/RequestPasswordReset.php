<?php

namespace App\Filament\Auth;

use App\Filament\Forms\Components\TurnstileCaptcha;
use App\Services\Auth\StaffPasswordResets;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * Lupa kata sandi petugas. Pesan selalu sama apa pun email yang diisi, agar akun terdaftar tidak bisa ditebak.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        try {
            $data = $this->form->getState();
        } catch (ValidationException $exception) {
            $this->dispatch(TurnstileCaptcha::RESET_EVENT);

            throw $exception;
        }

        app(StaffPasswordResets::class)->requestLink($data['email']);

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        Notification::make()
            ->title('Periksa email Anda')
            ->body("Bila email tersebut terdaftar sebagai akun petugas aktif, tautan atur ulang kata sandi sudah dikirim dan berlaku {$minutes} menit.")
            ->success()
            ->send();

        $this->form->fill();
        $this->dispatch(TurnstileCaptcha::RESET_EVENT);
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Lupa kata sandi?';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent()->helperText('Masukkan email akun petugas. Tautan atur ulang dikirim ke email tersebut.'),
            TurnstileCaptcha::make('captcha'),
        ]);
    }
}

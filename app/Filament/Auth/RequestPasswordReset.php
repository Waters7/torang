<?php

namespace App\Filament\Auth;

use App\Filament\Forms\Components\TurnstileCaptcha;
use App\Services\Auth\StaffPasswordResets;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
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
            ->body("Bila data tersebut terdaftar sebagai akun petugas aktif, tautan atur ulang kata sandi sudah dikirim ke email akun dan berlaku {$minutes} menit.")
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
            $this->getEmailFormComponent(),
            TurnstileCaptcha::make('captcha'),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email atau NIP')
            ->helperText('Tautan atur ulang dikirim ke email yang terdaftar pada akun petugas.')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }
}

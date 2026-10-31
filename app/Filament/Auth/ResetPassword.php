<?php

namespace App\Filament\Auth;

use App\Filament\Forms\Components\TurnstileCaptcha;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Atur ulang kata sandi dari tautan email, dengan captcha. Setelah berhasil, sesi lain akun ini ikut keluar
 * (AuthenticateSession membandingkan hash kata sandi).
 */
class ResetPassword extends BaseResetPassword
{
    /** Form halaman ini tanpa statePath, jadi token captcha disimpan di properti sendiri. */
    public ?string $captcha = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            TurnstileCaptcha::make('captcha'),
        ]);
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        try {
            $response = parent::resetPassword();
        } catch (ValidationException $exception) {
            $this->dispatch(TurnstileCaptcha::RESET_EVENT);

            throw $exception;
        }

        if ($response === null) {
            $this->dispatch(TurnstileCaptcha::RESET_EVENT);
        }

        return $response;
    }
}

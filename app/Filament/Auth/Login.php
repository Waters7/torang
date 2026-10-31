<?php

namespace App\Filament\Auth;

use App\Filament\Forms\Components\TurnstileCaptcha;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Masuk petugas dengan captcha Cloudflare Turnstile; captcha divalidasi sebelum kata sandi diperiksa.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
            TurnstileCaptcha::make('captcha'),
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $response = parent::authenticate();
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

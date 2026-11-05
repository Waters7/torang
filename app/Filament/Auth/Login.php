<?php

namespace App\Filament\Auth;

use App\Filament\Forms\Components\TurnstileCaptcha;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Masuk petugas dengan email atau NIP, dan captcha Cloudflare Turnstile; captcha divalidasi sebelum
 * kata sandi diperiksa.
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

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email atau NIP')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [...User::loginLookup($data['email']), 'password' => $data['password']];
    }
}

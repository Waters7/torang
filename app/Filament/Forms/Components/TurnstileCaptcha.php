<?php

namespace App\Filament\Forms\Components;

use App\Rules\Turnstile;
use App\Services\TurnstileVerifier;
use Filament\Forms\Components\Field;

/**
 * Widget Cloudflare Turnstile untuk form panel (masuk, lupa & atur ulang kata sandi). Token diverifikasi di
 * server saat form divalidasi; token hanya sekali pakai, jadi halaman mengirim RESET_EVENT setelah percobaan gagal.
 */
class TurnstileCaptcha extends Field
{
    public const RESET_EVENT = 'torang-turnstile-reset';

    protected string $view = 'filament.forms.components.turnstile-captcha';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Verifikasi keamanan')
            ->hiddenLabel()
            ->required()
            ->rule(fn (): Turnstile => new Turnstile(request()->ip()))
            ->validationMessages(['required' => 'Selesaikan captcha terlebih dahulu.'])
            ->dehydrated(false)
            ->visible(fn (): bool => app(TurnstileVerifier::class)->requiredForPanel());
    }

    public function getSiteKey(): ?string
    {
        return app(TurnstileVerifier::class)->siteKey();
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifikasi token Cloudflare Turnstile di sisi server.
 * Tanpa secret key, verifikasi selalu gagal (fail closed).
 */
class TurnstileVerifier
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(?string $token, ?string $ip = null): bool
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($secret) || blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key') ?: null;
    }

    public function configured(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /**
     * Captcha di halaman masuk & atur ulang kata sandi petugas: wajib bila kunci terisi, dan selalu wajib di
     * produksi (tanpa kunci verifikasi gagal, sama seperti form publik). Di lokal tanpa kunci dilewati.
     */
    public function requiredForPanel(): bool
    {
        return $this->configured() || app()->isProduction();
    }
}

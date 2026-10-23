<?php

namespace App\Rules;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Turnstile implements ValidationRule
{
    public function __construct(private readonly ?string $ip = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(TurnstileVerifier::class)->verify(is_string($value) ? $value : null, $this->ip)) {
            $fail('Verifikasi captcha gagal. Silakan ulangi centang captcha.');
        }
    }
}

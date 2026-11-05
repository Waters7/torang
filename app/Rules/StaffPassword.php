<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Kata sandi petugas: minimal 12 karakter (sama dengan form Pengguna) dan bukan NIP pemiliknya,
 * karena NIP dipakai sebagai kata sandi awal dan mudah diketahui orang lain.
 */
class StaffPassword implements ValidationRule
{
    public const MIN_LENGTH = 12;

    public function __construct(private readonly ?string $nip) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (mb_strlen((string) $value) < self::MIN_LENGTH) {
            $fail('Kata sandi minimal '.self::MIN_LENGTH.' karakter.');
        }

        if (filled($this->nip) && str_contains((string) $value, $this->nip)) {
            $fail('Kata sandi tidak boleh memuat NIP Anda.');
        }
    }
}

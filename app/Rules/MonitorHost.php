<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Host monitoring: hanya alamat IP atau hostname yang valid. Menolak spasi, karakter shell,
 * dan awalan "-" sehingga tidak bisa disalahgunakan sebagai argumen perintah ping.
 */
class MonitorHost implements ValidationRule
{
    private const HOSTNAME = '/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))*\.?$/';

    public static function isValid(mixed $host): bool
    {
        if (! is_string($host) || $host === '') {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false || preg_match(self::HOSTNAME, $host) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('Host harus berupa alamat IP atau nama host yang valid (contoh: 203.0.113.10 atau router.example.ac.id).');
        }
    }
}

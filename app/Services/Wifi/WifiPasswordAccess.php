<?php

namespace App\Services\Wifi;

use App\Models\User;
use App\Models\WifiNetwork;
use App\Models\WifiPasswordHistory;
use App\Support\Format;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Akses password WiFi: konfirmasi ulang password akun (berlaku `auth.password_timeout`, default 5 menit,
 * memakai penanda session yang sama dengan middleware password.confirm) dan pencatatan setiap lihat/salin/cetak
 * ke activity log beserta IP & user agent. Isi password tidak pernah ikut dicatat.
 */
class WifiPasswordAccess
{
    public const MAX_ATTEMPTS = 5;

    public const EVENT_REVEALED = 'password_revealed';

    public const EVENT_COPIED = 'password_copied';

    public const EVENT_QR_PRINTED = 'qr_printed';

    public const EVENT_CHANGED = 'password_changed';

    public function isConfirmed(): bool
    {
        $confirmedAt = session('auth.password_confirmed_at');

        return is_numeric($confirmedAt) && (now()->unix() - (int) $confirmedAt) < $this->timeoutSeconds();
    }

    public function timeoutSeconds(): int
    {
        return (int) config('auth.password_timeout', 300);
    }

    /**
     * Periksa password akun; maksimal 5 percobaan salah per menit.
     *
     * @return string|null Pesan kesalahan, atau null bila berhasil.
     */
    public function confirm(User $user, string $password): ?string
    {
        $key = 'wifi-password-confirm:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.';
        }

        if (! Hash::check($password, $user->getAuthPassword())) {
            RateLimiter::hit($key, 60);

            return 'Password akun tidak sesuai.';
        }

        RateLimiter::clear($key);
        session()->passwordConfirmed();

        return null;
    }

    public function logRevealed(WifiNetwork $network, ?WifiPasswordHistory $history = null): void
    {
        $this->log($network, self::EVENT_REVEALED, $history
            ? 'Melihat password lama (diganti '.Format::date($history->changed_at).')'
            : 'Melihat password', $history);
    }

    public function logCopied(WifiNetwork $network, ?WifiPasswordHistory $history = null): void
    {
        $this->log($network, self::EVENT_COPIED, $history
            ? 'Menyalin password lama (diganti '.Format::date($history->changed_at).')'
            : 'Menyalin password', $history);
    }

    public function logQrPrinted(WifiNetwork $network): void
    {
        $this->log($network, self::EVENT_QR_PRINTED, 'Mencetak kartu QR WiFi');
    }

    public function logChanged(WifiNetwork $network): void
    {
        $this->log($network, self::EVENT_CHANGED, 'Mengganti password');
    }

    private function log(WifiNetwork $network, string $event, string $description, ?WifiPasswordHistory $history = null): void
    {
        activity('wifi')
            ->performedOn($network)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties(array_filter([
                ...RequestContext::forActivity(),
                'history_id' => $history?->getKey(),
            ], fn ($value): bool => $value !== null))
            ->log($description);
    }
}

<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\Auth\StaffPasswordReset;
use App\Support\RequestContext;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;

/**
 * Lupa kata sandi petugas: tautan hanya dikirim ke akun aktif yang boleh masuk panel. Hasilnya tidak pernah
 * diberitahukan ke peminta (tanpa enumerasi akun); permintaan dan penggantian tercatat di audit log.
 */
class StaffPasswordResets
{
    public const EVENT_REQUESTED = 'password_reset_requested';

    public const EVENT_COMPLETED = 'password_reset';

    /**
     * @param  string  $identifier  Email atau NIP akun.
     */
    public function requestLink(string $identifier): void
    {
        $email = User::query()->where(User::loginLookup($identifier))->value('email');

        if ($email === null) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $email], function (User $user, #[SensitiveParameter] string $token): void {
            if (! $user->canAccessPanel(Filament::getPanel('admin'))) {
                return;
            }

            $user->notify(new StaffPasswordReset(Filament::getPanel('admin')->getResetPasswordUrl($token, $user)));

            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->event(self::EVENT_REQUESTED)
                ->withProperties(RequestContext::forActivity())
                ->log('Meminta tautan atur ulang kata sandi');
        });
    }

    public function recordCompleted(User $user): void
    {
        // Sandi baru dari tautan email sudah menggantikan kata sandi awal (NIP).
        $user->forceFill(['must_change_password' => false])->save();

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event(self::EVENT_COMPLETED)
            ->withProperties(RequestContext::forActivity())
            ->log('Kata sandi diatur ulang lewat tautan email');
    }
}

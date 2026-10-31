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

    public function requestLink(string $email): void
    {
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
        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event(self::EVENT_COMPLETED)
            ->withProperties(RequestContext::forActivity())
            ->log('Kata sandi diatur ulang lewat tautan email');
    }
}

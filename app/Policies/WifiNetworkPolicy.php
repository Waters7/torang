<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WifiNetwork;

class WifiNetworkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WifiView->value);
    }

    public function view(User $user, WifiNetwork $network): bool
    {
        return $user->can(Permission::WifiView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WifiManage->value);
    }

    public function update(User $user, WifiNetwork $network): bool
    {
        return $user->can(Permission::WifiManage->value);
    }

    public function delete(User $user, WifiNetwork $network): bool
    {
        return $user->can(Permission::WifiManage->value);
    }

    /**
     * Lihat/salin password (termasuk password lama dan kartu QR). Tetap butuh konfirmasi password akun.
     */
    public function revealPassword(User $user, WifiNetwork $network): bool
    {
        return $user->can(Permission::WifiRevealPassword->value);
    }
}

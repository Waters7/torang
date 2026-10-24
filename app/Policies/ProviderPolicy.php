<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Provider;
use App\Models\User;

class ProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::HostingView->value);
    }

    public function view(User $user, Provider $provider): bool
    {
        return $user->can(Permission::HostingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::HostingManage->value);
    }

    public function update(User $user, Provider $provider): bool
    {
        return $user->can(Permission::HostingManage->value);
    }

    /**
     * Provider yang masih punya layanan tidak bisa dihapus.
     */
    public function delete(User $user, Provider $provider): bool
    {
        return $user->can(Permission::HostingManage->value) && ! $provider->services()->exists();
    }
}

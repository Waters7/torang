<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\HostedService;
use App\Models\User;

class HostedServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::HostingView->value);
    }

    public function view(User $user, HostedService $service): bool
    {
        return $user->can(Permission::HostingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::HostingManage->value);
    }

    public function update(User $user, HostedService $service): bool
    {
        return $user->can(Permission::HostingManage->value);
    }

    /**
     * Layanan yang sudah punya riwayat pembayaran tidak dihapus; ubah statusnya menjadi Dihentikan.
     */
    public function delete(User $user, HostedService $service): bool
    {
        return $user->can(Permission::HostingManage->value) && ! $service->payments()->exists();
    }

    public function recordPayment(User $user, HostedService $service): bool
    {
        return $user->can(Permission::HostingManage->value);
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::HostingView->value);
    }
}

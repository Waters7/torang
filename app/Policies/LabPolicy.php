<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Lab;
use App\Models\User;

class LabPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LabsView->value);
    }

    public function view(User $user, Lab $lab): bool
    {
        return $user->can(Permission::LabsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LabsManage->value);
    }

    public function update(User $user, Lab $lab): bool
    {
        return $user->can(Permission::LabsManage->value);
    }

    /**
     * Lab yang sudah punya riwayat pengajuan tidak dihapus; ubah statusnya menjadi nonaktif.
     */
    public function delete(User $user, Lab $lab): bool
    {
        return $user->can(Permission::LabsManage->value) && ! $lab->bookings()->withTrashed()->exists();
    }
}

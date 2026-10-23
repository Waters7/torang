<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StaffManage->value);
    }

    public function view(User $user, Staff $staff): bool
    {
        return $user->can(Permission::StaffManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StaffManage->value);
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->can(Permission::StaffManage->value);
    }

    /**
     * Petugas yang punya riwayat tugas dinonaktifkan saja, tidak dihapus.
     */
    public function delete(User $user, Staff $staff): bool
    {
        return $user->can(Permission::StaffManage->value) && ! $staff->assignments()->exists();
    }
}

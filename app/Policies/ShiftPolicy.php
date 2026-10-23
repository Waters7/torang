<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StaffManage->value);
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->can(Permission::StaffManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StaffManage->value);
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->can(Permission::StaffManage->value);
    }

    /**
     * Shift yang sudah dipakai di jadwal dinonaktifkan saja, tidak dihapus.
     */
    public function delete(User $user, Shift $shift): bool
    {
        return $user->can(Permission::StaffManage->value) && ! $shift->assignments()->exists();
    }
}

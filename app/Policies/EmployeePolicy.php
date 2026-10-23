<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;

/**
 * Data pegawai berasal dari impor DUK, jadi tidak dibuat/diubah manual.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EmployeesManage->value);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can(Permission::EmployeesManage->value);
    }

    public function import(User $user): bool
    {
        return $user->can(Permission::EmployeesManage->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Employee $employee): bool
    {
        return false;
    }

    public function delete(User $user, Employee $employee): bool
    {
        return false;
    }
}

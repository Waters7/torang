<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Monitor;
use App\Models\User;

class MonitorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MonitorsView->value);
    }

    public function view(User $user, Monitor $monitor): bool
    {
        return $user->can(Permission::MonitorsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MonitorsManage->value);
    }

    public function update(User $user, Monitor $monitor): bool
    {
        return $user->can(Permission::MonitorsManage->value);
    }

    public function delete(User $user, Monitor $monitor): bool
    {
        return $user->can(Permission::MonitorsManage->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permission::MonitorsManage->value);
    }

    public function checkNow(User $user, Monitor $monitor): bool
    {
        return $user->can(Permission::MonitorsManage->value) && $monitor->is_active;
    }
}

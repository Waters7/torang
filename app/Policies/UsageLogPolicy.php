<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\UsageLog;
use App\Models\User;

class UsageLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BookingsView->value) || $user->can(Permission::UsageLogsManage->value);
    }

    public function view(User $user, UsageLog $usageLog): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsageLogsManage->value);
    }

    public function update(User $user, UsageLog $usageLog): bool
    {
        return $user->can(Permission::UsageLogsManage->value);
    }

    public function delete(User $user, UsageLog $usageLog): bool
    {
        return $user->can(Permission::UsageLogsManage->value);
    }
}

<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can(Permission::AnnouncementsManage->value);
    }

    public function view(User $user, Announcement $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Announcement $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Announcement $record): bool
    {
        return $this->viewAny($user);
    }
}

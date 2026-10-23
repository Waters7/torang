<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DutyWeek;
use App\Models\User;

class DutyWeekPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DutyManage->value) || $user->can(Permission::DutyPublish->value);
    }

    public function view(User $user, DutyWeek $dutyWeek): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::DutyManage->value);
    }

    /**
     * Mengisi grid. Penguncian jadwal terbit ditangani di action (DutyScheduleException).
     */
    public function update(User $user, DutyWeek $dutyWeek): bool
    {
        return $user->can(Permission::DutyManage->value);
    }

    public function delete(User $user, DutyWeek $dutyWeek): bool
    {
        return $user->can(Permission::DutyManage->value) && $dutyWeek->published_at === null;
    }

    public function publish(User $user, DutyWeek $dutyWeek): bool
    {
        return $user->can(Permission::DutyPublish->value);
    }

    public function unpublish(User $user, DutyWeek $dutyWeek): bool
    {
        return $user->can(Permission::DutyPublish->value);
    }

    public function downloadPdf(User $user, DutyWeek $dutyWeek): bool
    {
        return $this->viewAny($user);
    }
}

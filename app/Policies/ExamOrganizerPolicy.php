<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExamOrganizer;
use App\Models\User;

class ExamOrganizerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    public function view(User $user, ExamOrganizer $organizer): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    public function update(User $user, ExamOrganizer $organizer): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    /**
     * Kolegium yang sudah dipakai laporan tidak dihapus; nonaktifkan saja.
     */
    public function delete(User $user, ExamOrganizer $organizer): bool
    {
        return $user->can(Permission::ReportsUkomManage->value) && ! $organizer->reports()->withTrashed()->exists();
    }
}

<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MonitorIncident;
use App\Models\User;

/**
 * Insiden dibuat/ditutup otomatis oleh mesin monitoring; petugas hanya mengisi catatan.
 */
class MonitorIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MonitorsView->value);
    }

    public function view(User $user, MonitorIncident $incident): bool
    {
        return $user->can(Permission::MonitorsView->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MonitorIncident $incident): bool
    {
        return $user->can(Permission::MonitorsManage->value);
    }

    public function delete(User $user, MonitorIncident $incident): bool
    {
        return false;
    }
}

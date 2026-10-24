<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\UkomReport;
use App\Models\User;

class UkomReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    public function view(User $user, UkomReport $report): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    /**
     * Laporan yang sudah difinalkan terkunci; koreksi lewat Duplikat.
     */
    public function update(User $user, UkomReport $report): bool
    {
        return $user->can(Permission::ReportsUkomManage->value) && ! $report->isLocked() && ! $report->trashed();
    }

    public function finalize(User $user, UkomReport $report): bool
    {
        return $this->update($user, $report);
    }

    public function uploadSignedScan(User $user, UkomReport $report): bool
    {
        return $user->can(Permission::ReportsUkomManage->value) && $report->isLocked() && ! $report->trashed();
    }

    public function duplicate(User $user, UkomReport $report): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    public function download(User $user, UkomReport $report): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    /**
     * "Arsipkan" (soft delete). Laporan tidak pernah dihapus permanen agar nomor surat tidak terpakai ulang.
     */
    public function delete(User $user, UkomReport $report): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, UkomReport $report): bool
    {
        return $user->can(Permission::ReportsUkomManage->value);
    }

    public function forceDelete(User $user, UkomReport $report): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

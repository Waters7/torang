<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\User;
use App\Services\SettingsService;

class BookingPolicy
{
    public function __construct(private readonly SettingsService $settings) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BookingsView->value);
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can(Permission::BookingsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BookingsManage->value);
    }

    /**
     * Hanya pengajuan yang masih memblokir slot (menunggu/disetujui) yang boleh diubah.
     */
    public function update(User $user, Booking $booking): bool
    {
        return $user->can(Permission::BookingsManage->value) && $booking->status->isBlocking();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->can(Permission::BookingsManage->value);
    }

    /**
     * Menghapus hanya untuk pengajuan yang sudah tidak aktif (ditolak/dibatalkan/selesai).
     */
    public function delete(User $user, Booking $booking): bool
    {
        return $user->can(Permission::BookingsManage->value) && ! $booking->status->isBlocking();
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permission::BookingsManage->value);
    }

    public function restore(User $user, Booking $booking): bool
    {
        return $user->can(Permission::BookingsManage->value);
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        return false;
    }

    /**
     * Penyetuju = punya permission bookings.approve DAN role-nya ada di setting bookings.approver_roles.
     */
    public function approveAny(User $user): bool
    {
        return $user->can(Permission::BookingsApprove->value)
            && $user->hasAnyRole((array) $this->settings->get('bookings.approver_roles', []));
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $this->approveAny($user);
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $this->approveAny($user);
    }

    public function downloadAttachment(User $user, Booking $booking): bool
    {
        return $user->can(Permission::BookingsView->value);
    }

    public function recordUsage(User $user, Booking $booking): bool
    {
        return $user->can(Permission::UsageLogsManage->value);
    }
}

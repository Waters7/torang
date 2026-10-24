<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ServicePayment;
use App\Models\User;

class ServicePaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::HostingView->value);
    }

    public function view(User $user, ServicePayment $payment): bool
    {
        return $user->can(Permission::HostingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::HostingManage->value);
    }

    public function update(User $user, ServicePayment $payment): bool
    {
        return $user->can(Permission::HostingManage->value);
    }

    public function delete(User $user, ServicePayment $payment): bool
    {
        return $user->can(Permission::HostingManage->value);
    }

    public function downloadProof(User $user, ServicePayment $payment): bool
    {
        return $user->can(Permission::HostingView->value) && filled($payment->proof_path);
    }
}

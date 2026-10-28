<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can(Permission::TicketsView->value);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can(Permission::TicketsManage->value);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false;
    }
}

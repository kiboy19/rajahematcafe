<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $this->isAdmin($user) && $po->isEditable();
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $this->isAdmin($user) && $po->isEditable();
    }

    public function submit(User $user, PurchaseOrder $po): bool
    {
        return $this->isAdmin($user);
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        // Approval hanya untuk Owner atau Manager
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true) && $user->is_active;
    }

    public function receive(User $user, PurchaseOrder $po): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true) && $user->is_active;
    }
}

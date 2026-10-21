<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, MenuItem $menuItem): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, MenuItem $menuItem): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDelete(User $user, MenuItem $menuItem): bool
    {
        return false;
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true) && $user->is_active;
    }
}

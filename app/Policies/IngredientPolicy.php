<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\User;

class IngredientPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Ingredient $ingredient): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Ingredient $ingredient): bool
    {
        return $this->isAdmin($user);
    }

    public function recordMovement(User $user, Ingredient $ingredient): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true) && $user->is_active;
    }
}

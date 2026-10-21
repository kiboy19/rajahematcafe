<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\User;

class CafeTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, CafeTable $cafeTable): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, CafeTable $cafeTable): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, CafeTable $cafeTable): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, CafeTable $cafeTable): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDelete(User $user, CafeTable $cafeTable): bool
    {
        return false;
    }

    /**
     * Rotasi token QR hanya untuk owner/manager (aksi sensitif, membatalkan QR lama).
     */
    public function rotateQr(User $user, CafeTable $cafeTable): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true) && $user->is_active;
    }
}

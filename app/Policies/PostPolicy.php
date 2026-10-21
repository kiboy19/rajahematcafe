<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Staf aktif dengan role owner atau manager dapat melihat daftar artikel di panel admin.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true) && $user->is_active;
    }

    public function view(User $user, Post $post): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Post $post): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->viewAny($user);
    }
}

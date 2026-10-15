<?php

namespace App\Policies;

use App\Models\User;
use App\Security\Role;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === Role::Owner;
    }
}

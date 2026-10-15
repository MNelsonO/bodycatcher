<?php

namespace App\Settings\Policies;

use App\Models\User;
use App\Security\Role;
use App\Settings\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->role === Role::Owner;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->role === Role::Owner;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->role === Role::Owner;
    }
}

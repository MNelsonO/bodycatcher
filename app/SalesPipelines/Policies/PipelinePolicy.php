<?php

namespace App\SalesPipelines\Policies;

use App\Models\User;
use App\SalesPipelines\Pipeline;
use App\Security\Role;

class PipelinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        return $user->role === Role::Owner;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return $user->role === Role::Owner;
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return $user->role === Role::Owner;
    }
}

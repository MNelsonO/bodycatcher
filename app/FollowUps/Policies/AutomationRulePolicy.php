<?php

namespace App\FollowUps\Policies;

use App\FollowUps\AutomationRule;
use App\Models\User;
use App\Security\Role;

class AutomationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->desk($user);
    }

    public function view(User $user, AutomationRule $rule): bool
    {
        return $this->desk($user);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    public function update(User $user, AutomationRule $rule): bool
    {
        return $user->role === Role::Owner;
    }

    private function desk(User $user): bool
    {
        return match ($user->role) {
            Role::Owner, Role::Sales => true,
        };
    }
}

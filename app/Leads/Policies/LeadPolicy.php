<?php

namespace App\Leads\Policies;

use App\Leads\Lead;
use App\Models\User;
use App\Security\Role;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->desk($user);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->desk($user);
    }

    public function create(User $user): bool
    {
        return $this->desk($user);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->desk($user);
    }

    private function desk(User $user): bool
    {
        return match ($user->role) {
            Role::Owner, Role::Sales => true,
        };
    }
}

<?php

namespace App\WhatsApp\Policies;

use App\Models\User;
use App\Security\Role;

class DeskSettingPolicy
{
    public function view(User $user): bool
    {
        return match ($user->role) {
            Role::Owner, Role::Sales => true,
        };
    }

    public function update(User $user): bool
    {
        return $user->isOwner();
    }
}

<?php

namespace App\Contacts\Policies;

use App\Contacts\Contact;
use App\Models\User;
use App\Security\Role;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->desk($user);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->desk($user);
    }

    public function create(User $user): bool
    {
        return $this->desk($user);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->desk($user);
    }

    public function merge(User $user, Contact $contact): bool
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

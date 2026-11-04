<?php

namespace App\Calendar\Policies;

use App\Calendar\Appointment;
use App\Models\User;
use App\Security\Role;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->desk($user);
    }

    public function create(User $user): bool
    {
        return $this->desk($user);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->desk($user);
    }

    public function delete(User $user, Appointment $appointment): bool
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

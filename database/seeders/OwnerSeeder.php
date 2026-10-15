<?php

namespace Database\Seeders;

use App\Models\User;
use App\Security\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('kemet.seed_user_email');
        $password = config('kemet.seed_user_password');
        $name = config('kemet.seed_user_name');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Set SEED_USER_EMAIL and SEED_USER_PASSWORD before seeding the owner.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => is_string($name) && $name !== '' ? $name : 'Nelson Mboya',
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $user->role = Role::Owner;
        $user->save();
    }
}

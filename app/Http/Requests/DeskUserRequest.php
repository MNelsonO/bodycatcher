<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Security\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DeskUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        if ($user instanceof User) {
            return $this->user()?->can('update', $user) ?? false;
        }

        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $creating = ! $user instanceof User;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [
                $creating ? 'required' : 'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user instanceof User ? $user->id : null),
            ],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }
}

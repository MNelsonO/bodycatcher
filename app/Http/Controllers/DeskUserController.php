<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeskUserRequest;
use App\Models\User;
use App\Security\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeskUserController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('users/index', [
            'users' => User::query()->orderBy('name')->get()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ]),
            'roles' => collect(Role::cases())->map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => ucfirst($role->value),
            ])->all(),
        ]);
    }

    public function store(DeskUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'email_verified_at' => now(),
        ]);

        $user->role = Role::from($request->string('role')->toString());
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User added.']);

        return redirect()->route('users.index');
    }

    public function update(DeskUserRequest $request, User $user): RedirectResponse
    {
        $role = Role::from($request->string('role')->toString());

        if ($user->isOwner() && $role !== Role::Owner) {
            $owners = User::query()->where('role', Role::Owner->value)->count();

            if ($owners < 2) {
                throw ValidationException::withMessages([
                    'role' => 'Keep at least one owner on the desk.',
                ]);
            }
        }

        if ($request->filled('name')) {
            $user->name = $request->string('name')->toString();
        }

        if ($request->filled('email')) {
            $user->email = $request->string('email')->toString();
        }

        if ($request->filled('password')) {
            $user->password = $request->string('password')->toString();
        }

        $user->role = $role;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return redirect()->route('users.index');
    }
}

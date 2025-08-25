<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleUsersController extends Controller
{
    /**
     * Display a listing of the users by role.
     */
    public function index(Request $request, string $role): Response
    {
        // Ensure only valid roles are used (adjust if you have more)
        if (!in_array($role, ['Photographer', 'PhotoEditor'])) {
            abort(404);
        }

        $users = User::query()
            ->role($role) // spatie/laravel-permission scope
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        $page = match ($role) {
            'Photographer' => 'Users/Photographers',
            'PhotoEditor' => 'Users/PhotoEditors',
        };

        return Inertia::render($page, [
            'users' => $users,
            'role' => $role,
        ]);
    }

    /**
     * Store a newly created user under a role.
     */
    public function store(Request $request, string $role): RedirectResponse
    {
        if (!in_array($role, ['Photographer', 'PhotoEditor'])) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = bcrypt($validated['password']);
        $user->save();

        // assign role
        $user->assignRole($role);

        return back()->with('status', __('User created.'));
    }

    /**
     * Update the specified user under a role.
     */
    public function update(Request $request, string $role, User $user): RedirectResponse
    {
        if (!in_array($role, ['Photographer', 'PhotoEditor'])) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }
        $user->save();

        // Ensure role is set (in case user created elsewhere)
        if (!$user->hasRole($role)) {
            $user->syncRoles([$role]);
        }

        return back()->with('status', __('User updated.'));
    }
}

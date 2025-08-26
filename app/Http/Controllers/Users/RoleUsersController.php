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
            ->select(['id', 'email', 'last_name', 'first_name', 'middle_name', 'is_blocked', 'created_at'])
            ->orderBy('last_name')
            ->orderBy('first_name')
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'is_blocked' => ['sometimes', 'boolean'],
        ]);

        $user = new User();
        $user->email = $validated['email'];
        $user->password = bcrypt($validated['password']);
        $user->last_name = $validated['last_name'] ?? null;
        $user->first_name = $validated['first_name'] ?? null;
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->is_blocked = (bool) ($request->boolean('is_blocked', false));
        $user->save();

        // assign role
        $user->assignRole($role);

        return back()->with('status', __('User created.'));
    }

    /**
     * Update the specified user under a role.
     */
    public function update(Request $request, User $user, string $role): RedirectResponse
    {
        if (!in_array($role, ['Photographer', 'PhotoEditor'])) {
            abort(404);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'is_blocked' => ['sometimes', 'boolean'],
        ]);

        $user->email = $validated['email'];
        $user->last_name = $validated['last_name'] ?? null;
        $user->first_name = $validated['first_name'] ?? null;
        $user->middle_name = $validated['middle_name'] ?? null;
        if ($request->has('is_blocked')) {
            $user->is_blocked = (bool) $request->boolean('is_blocked');
        }
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

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user, string $role): RedirectResponse
    {
        if (!in_array($role, ['Photographer', 'PhotoEditor'])) {
            abort(404);
        }

        // Ensure the user actually has this role (to avoid deleting wrong target)
        if (! $user->hasRole($role)) {
            abort(404);
        }

        // If you prefer soft deletes, switch to $user->delete(); (model must use SoftDeletes)
        $user->delete();

        return back()->with('status', __('User deleted.'));
    }
}

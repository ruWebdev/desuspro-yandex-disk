<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Display the manager's user management page.
     */
    public function index(Request $request): Response
    {
        $manager = $request->user();

        $managedUsers = $manager->managedUsers()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email']);

        $assignableUsers = User::role(['Photographer', 'PhotoEditor'])
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Manager/Users/Index', [
            'managedUsers' => $managedUsers,
            'assignableUsers' => $assignableUsers,
            'roles' => ['Photographer', 'PhotoEditor'],
        ]);
    }

    /**
     * Create a new Photographer or PhotoEditor and attach to the manager.
     */
    public function store(Request $request)
    {
        $manager = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(['Photographer', 'PhotoEditor'])],
        ]);

        $password = $data['password'] ?? 'password';

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $password,
        ]);
        $user->assignRole($data['role']);

        $manager->managedUsers()->syncWithoutDetaching([$user->id]);

        return back()->with('success', 'User created and assigned.');
    }

    /**
     * Attach existing user to this manager.
     */
    public function attach(Request $request, User $user)
    {
        $manager = $request->user();

        // Optional: ensure user has one of allowed roles
        if (! $user->hasAnyRole(['Photographer', 'PhotoEditor'])) {
            return back()->with('error', 'User does not have an assignable role.');
        }

        $manager->managedUsers()->syncWithoutDetaching([$user->id]);

        return back()->with('success', 'User attached.');
    }

    /**
     * Detach user from this manager.
     */
    public function detach(Request $request, User $user)
    {
        $manager = $request->user();
        $manager->managedUsers()->detach([$user->id]);

        return back()->with('success', 'User detached.');
    }
}

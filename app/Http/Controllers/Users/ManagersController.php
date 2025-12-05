<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Task;
use App\Models\Subtask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagersController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->with('roles:id,name')
            ->role('Manager')
            ->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('first_name', 'like', "%$search%")
                    ->orWhere('middle_name', 'like', "%$search%");
            });
        }

        $users = $query->get(['id', 'name', 'email', 'last_name', 'first_name', 'middle_name', 'is_blocked', 'can_edit_result', 'created_at']);

        return Inertia::render('Admin/Users/Managers', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'is_blocked' => 'sometimes|boolean',
            'can_edit_result' => 'sometimes|boolean',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'last_name' => $data['last_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'is_blocked' => (bool)($data['is_blocked'] ?? false),
            'can_edit_result' => (bool)($data['can_edit_result'] ?? false),
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('Manager');
        }

        return back()->with('status', 'manager-created');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Ensure the user is a manager
        if (!$user->hasRole('Manager')) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'is_blocked' => 'sometimes|boolean',
            'can_edit_result' => 'sometimes|boolean',
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'last_name' => $data['last_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'is_blocked' => (bool)($data['is_blocked'] ?? $user->is_blocked),
            'can_edit_result' => (bool)($data['can_edit_result'] ?? $user->can_edit_result),
        ]);

        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

        $user->save();
        return back()->with('status', 'manager-updated');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (!$user->hasRole('Manager')) {
            abort(403);
        }

        // Check if manager has assigned tasks or subtasks
        $hasTasks = Task::where('assignee_id', $user->id)->exists();
        $hasSubtasks = Subtask::where('assignee_id', $user->id)->exists();

        if ($hasTasks || $hasSubtasks) {
            return back()->withErrors([
                'delete' => 'Невозможно удалить менеджера, так как ему назначены задания.'
            ]);
        }

        $user->delete();
        return back()->with('status', 'manager-deleted');
    }
}

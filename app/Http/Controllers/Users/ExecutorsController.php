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

class ExecutorsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->with('roles:id,name')
            ->whereDoesntHave('roles', function($q) { $q->where('name', 'Manager'); })
            ->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('first_name', 'like', "%$search%")
                  ->orWhere('middle_name', 'like', "%$search%")
                ;
            });
        }

        $users = $query->get(['id','name','email','last_name','first_name','middle_name','is_blocked','created_at']);

        return Inertia::render('Users/Executors', [
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
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'last_name' => $data['last_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'is_blocked' => (bool)($data['is_blocked'] ?? false),
        ]);
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('Performer');
        }
        return back()->with('status', 'executor-created');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Do not allow editing Managers via this controller
        if ($user->hasRole('Manager')) {
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
        ]);
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'last_name' => $data['last_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'is_blocked' => (bool)($data['is_blocked'] ?? $user->is_blocked),
        ]);
        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }
        $user->save();
        return back()->with('status', 'executor-updated');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->hasRole('Manager')) {
            abort(403);
        }

        // Check if user has assigned tasks or subtasks
        $hasTasks = Task::where('assignee_id', $user->id)->exists();
        $hasSubtasks = Subtask::where('assignee_id', $user->id)->exists();

        if ($hasTasks || $hasSubtasks) {
            return back()->withErrors([
                'delete' => 'Невозможно удалить пользователя, так как ему назначены задания.'
            ]);
        }

        $user->delete();
        return back()->with('status', 'executor-deleted');
    }
}

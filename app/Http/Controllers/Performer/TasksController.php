<?php

namespace App\Http\Controllers\Performer;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TasksController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tasks = Task::query()
            ->where('assignee_id', $user->id)
            ->whereIn('ownership', ['Photographer','PhotoEditor'])
            ->with([
                'brand:id,name',
                'article:id,name',
                'type:id,name',
            ])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'brand_id',
                'article_id',
                'task_type_id',
                'name',
                'status',
                'ownership',
                'assignee_id',
                'public_link',
                'highlighted',
                'comment',
                'size',
                'created_at',
            ]);

        return Inertia::render('Performer/Tasks', [
            'tasks' => $tasks,
        ]);
    }

    /**
     * Allow performer (assignee) to update own task status (e.g., send for review).
     */
    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $request->validate([
            'status' => ['required','string','in:on_review'],
        ]);

        // Ensure only the assignee can change status this way
        abort_unless($request->user()->id === $task->assignee_id, 403);

        $task->update(['status' => $request->string('status')]);

        return back(303);
    }
}

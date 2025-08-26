<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Task;
use App\Models\Subtask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubtaskController extends Controller
{
    /**
     * List subtasks for a task (JSON response for now; UI can be added later).
     */
    public function index(Request $request, Brand $brand, Task $task): JsonResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $subtasks = Subtask::query()
            ->where('task_id', $task->id)
            ->with(['assignee:id,name'])
            ->orderByDesc('created_at')
            ->get(['id','task_id','name','status','ownership','assignee_id','public_link','highlighted','comment','size','created_at']);

        return response()->json([
            'brand' => $brand->only(['id','name']),
            'task' => $task->only(['id','name','brand_id']),
            'subtasks' => $subtasks,
        ]);
    }

    /**
     * Create a subtask under a task.
     */
    public function store(Request $request, Brand $brand, Task $task): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $data = $request->validate([
            'name' => ['nullable','string','max:255'],
            'status' => ['nullable','in:created,accepted,rejected'],
            'ownership' => ['nullable','in:Photographer,PhotoEditor'],
            'assignee_id' => ['nullable','exists:users,id'],
            'comment' => ['nullable','string'],
            'highlighted' => ['sometimes','boolean'],
        ]);

        Subtask::create([
            'task_id' => $task->id,
            'name' => $data['name'] ?? null,
            'status' => $data['status'] ?? 'created',
            'ownership' => $data['ownership'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'comment' => $data['comment'] ?? null,
            'highlighted' => (bool) ($data['highlighted'] ?? false),
        ]);

        return back()->with('status', 'subtask-created');
    }

    /**
     * Update a subtask.
     */
    public function update(Request $request, Brand $brand, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id && $subtask->task_id === $task->id, 404);
        $data = $request->validate([
            'name' => ['sometimes','nullable','string','max:255'],
            'status' => ['sometimes','required','in:created,accepted,rejected'],
            'ownership' => ['sometimes','nullable','in:Photographer,PhotoEditor'],
            'assignee_id' => ['sometimes','nullable','exists:users,id'],
            'comment' => ['sometimes','nullable','string'],
            'highlighted' => ['sometimes','boolean'],
        ]);
        $subtask->fill($data)->save();
        return back()->with('status', 'subtask-updated');
    }

    /**
     * Delete a subtask.
     */
    public function destroy(Brand $brand, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id && $subtask->task_id === $task->id, 404);
        $subtask->delete();
        return back()->with('status', 'subtask-deleted');
    }

    /**
     * Generate a public link for subtask.
     */
    public function generatePublicLink(Brand $brand, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id && $subtask->task_id === $task->id, 404);
        if (!$subtask->public_link) {
            $subtask->public_link = url('/share/subtasks/'.$subtask->id.'/'.bin2hex(random_bytes(8)));
            $subtask->save();
        }
        return back()->with('status', 'subtask-public-link-created');
    }

    /**
     * Remove public link for subtask.
     */
    public function removePublicLink(Brand $brand, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id && $subtask->task_id === $task->id, 404);
        $subtask->public_link = null;
        $subtask->save();
        return back()->with('status', 'subtask-public-link-removed');
    }
}

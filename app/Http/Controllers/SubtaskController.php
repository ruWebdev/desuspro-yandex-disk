<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Task;
use App\Models\Subtask;
use App\Models\YandexToken;
use App\Services\YandexDiskService;
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
            // Support extended statuses
            'status' => ['nullable','in:created,unassigned,assigned,on_review,accepted,rejected'],
            'ownership' => ['nullable','in:Photographer,PhotoEditor'],
            'assignee_id' => ['nullable','exists:users,id'],
            'comment' => ['nullable','string'],
            'highlighted' => ['sometimes','boolean'],
        ]);

        Subtask::create([
            'task_id' => $task->id,
            'name' => $data['name'] ?? null,
            // default to 'unassigned' if not provided
            'status' => $data['status'] ?? 'unassigned',
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
            // Support extended statuses
            'status' => ['sometimes','required','in:created,unassigned,assigned,on_review,accepted,rejected'],
            'ownership' => ['sometimes','nullable','in:Photographer,PhotoEditor'],
            'assignee_id' => ['sometimes','nullable','exists:users,id'],
            'comment' => ['sometimes','nullable','string'],
            'highlighted' => ['sometimes','boolean'],
        ]);
        // If assignee_id changes and status not explicitly provided, set status accordingly
        if (array_key_exists('assignee_id', $data) && !array_key_exists('status', $data)) {
            $data['status'] = $data['assignee_id'] ? 'assigned' : 'unassigned';
        }

        // Capture originals before change
        $originalName = $subtask->name;
        $originalOwnership = $subtask->ownership;

        // Compute new values without persisting yet
        $newName = array_key_exists('name', $data) ? $data['name'] : $subtask->name;
        $newOwnership = array_key_exists('ownership', $data) ? $data['ownership'] : $subtask->ownership;

        // Attempt Yandex Disk move when name/ownership changed and names are present
        $fromPath = null; $toPath = null;
        $brandRoot = $brand->name; // Root folder is the brand name

        $prefix = function (?string $ownership): string {
            return $ownership === 'Photographer' ? 'ф_' : ($ownership === 'PhotoEditor' ? 'д_' : '');
        };

        if (!empty($originalName) && !empty($newName)) {
            $oldPrefix = $prefix($originalOwnership);
            $newPrefix = $prefix($newOwnership);
            $fromPath = $brandRoot.'/'.($oldPrefix.$originalName);
            $toPath = $brandRoot.'/'.($newPrefix.$newName);
        }

        // Persist changes first; move does not depend on DB transaction here
        $subtask->fill($data)->save();

        if ($fromPath && $toPath && $fromPath !== $toPath) {
            try {
                $token = YandexToken::orderByDesc('updated_at')->first();
                if ($token) {
                    $service = app(YandexDiskService::class);
                    $token = $service->ensureValidToken($token);
                    $service->moveResource($token->access_token, $fromPath, $toPath, false);
                }
                // If there is no token or move fails silently, we just continue; UI can refresh later
            } catch (\Throwable $e) {
                // Swallow Yandex errors for now to not block subtask update
                // Consider logging: \Log::warning('Yandex move failed', [...]) if logger available
            }
        }
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

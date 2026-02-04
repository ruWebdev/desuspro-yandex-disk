<?php

namespace App\Http\Controllers\Performer;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
// use Illuminate\Http\RedirectResponse; // removed: method may return JSON or redirect
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
                'type:id,name,prefix',
                'creator:id,name',
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
                'created_by',
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
     * JSON search for performer's own tasks with filters + pagination (20 per page by default).
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $q = Task::query()
            ->where('assignee_id', $user->id)
            ->whereIn('ownership', ['Photographer','PhotoEditor'])
            ->with([
                'brand:id,name',
                'article:id,name',
                'type:id,name,prefix',
                'creator:id,name',
            ])
            ->orderByDesc('created_at');

        // Optional filters
        if ($request->filled('brand_id')) $q->where('brand_id', (int)$request->query('brand_id'));
        if ($request->filled('article')) {
            $text = trim((string)$request->query('article'));
            if ($text !== '') {
                $q->whereHas('article', fn($aq) => $aq->where('name', 'like', "%{$text}%"));
            }
        }
        if ($request->filled('status')) {
            $status = (string)$request->query('status');
            if ($status !== 'all') $q->where('status', $status);
        }
        if ($request->filled('priority')) {
            $priority = (string)$request->query('priority');
            if (in_array($priority, ['low','medium','high','urgent'], true)) {
                $q->where('priority', $priority);
            }
        }
        // Handle both 'search' and 'global_search' parameters
        $searchTerm = $request->filled('global_search') 
            ? $request->query('global_search') 
            : ($request->filled('search') ? $request->query('search') : null);
            
        if ($searchTerm) {
            $s = trim((string)$searchTerm);
            $q->where(function($qq) use ($s) {
                $qq->where('name', 'like', "%{$s}%")
                   ->orWhereHas('brand', fn($bq) => $bq->where('name', 'like', "%{$s}%"))
                   ->orWhere('comment', 'like', "%{$s}%")
                   ->orWhereHas('article', fn($aq) => $aq->where('name', 'like', "%{$s}%"));
            });
        }
        // Created date
        $created = $request->query('created');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $singleDate = $request->query('date');

        if (in_array($created, ['today','yesterday'], true)
            || ($created === 'date' && ($singleDate || $dateFrom || $dateTo))) {
            $today = now();
            if ($created === 'today') {
                $q->whereDate('created_at', $today->toDateString());
            } elseif ($created === 'yesterday') {
                $q->whereDate('created_at', $today->copy()->subDay()->toDateString());
            } elseif ($created === 'date') {
                if ($dateFrom && $dateTo) {
                    $q->whereDate('created_at', '>=', $dateFrom)
                      ->whereDate('created_at', '<=', $dateTo);
                } elseif ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                } elseif ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                } elseif ($singleDate) {
                    $q->whereDate('created_at', $singleDate);
                }
            }
        }

        $perPage = (int)($request->query('per_page', 20));
        $page = (int)($request->query('page', 1));
        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ]);
    }

    /**
     * Allow performer (assignee) to update own task status (e.g., start work, ask question, send for review).
     */
    public function updateStatus(Request $request, Task $task)
    {
        // Ensure only the assignee can change status this way
        abort_unless($request->user()->id === $task->assignee_id, 403);

        // Guard immutable statuses
        if (in_array($task->status, ['accepted', 'cancelled', 'done'], true)) {
            return $this->statusUpdateError($request, 'Task status can no longer be changed');
        }

        $data = $request->validate([
            'status' => ['required','string','in:in_progress,question,on_review'],
            'comment' => ['nullable','string'],
        ]);

        // If performer marks a question, require a comment
        if ($data['status'] === 'question') {
            $comment = trim((string)($data['comment'] ?? ''));
            if ($comment === '') {
                return $this->statusUpdateError($request, 'Comment is required when setting status to question');
            }
            $task->comment = $comment;
        }

        $task->status = $data['status'];
        $task->save();

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'task' => $task->fresh(),
            ]);
        }

        return back(303);
    }

    private function statusUpdateError(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['success' => false, 'error' => $message], 422);
        }
        return back()->withErrors(['status' => $message]);
    }
}

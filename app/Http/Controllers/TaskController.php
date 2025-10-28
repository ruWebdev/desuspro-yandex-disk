<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Task;
use App\Models\TaskFileThumbnail;
use App\Models\TaskSourceComment;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Subtask;
use App\Models\TaskType;
use App\Models\Article;
use App\Models\YandexToken;
use App\Services\YandexDiskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

class TaskController extends Controller
{
    public function __construct(private readonly YandexDiskService $disk)
    {
        // Убедиться, что маршруты, использующие этот контроллер, требуют аутентификации в маршрутах, если необходимо.
    }

    /**
     * Конечная точка поиска JSON для менеджера: фильтры на стороне сервера + пагинация (по умолчанию 20 на страницу).
     */
    public function search(Request $request)
    {
        $q = Task::query()
            ->with(['brand:id,name','type:id,name,prefix','article:id,name','assignee:id,name','creator:id,name'])
            ->orderByDesc('created_at');

        // Фильтрация на основе ролей
        $user = $request->user();
        if ($user && method_exists($user, 'hasRole')) {
            if ($user->hasRole('Administrator') || $user->hasRole('admin')) {
                // Администратор видит все задачи (без фильтра)
            } elseif ($user->hasRole('Manager') || $user->hasRole('manager')) {
                $q->where('created_by', $user->id);
            } elseif ($user->hasRole('Performer') || $user->hasRole('performer')) {
                $q->where('assignee_id', $user->id);
            }
        }

        // Фильтры
        if ($request->filled('brand_id')) $q->where('brand_id', (int)$request->query('brand_id'));
        if ($request->filled('article_id')) $q->where('article_id', (int)$request->query('article_id'));
        if ($request->filled('assignee_id')) $q->where('assignee_id', (int)$request->query('assignee_id'));
        if ($request->filled('priority')) {
            $priority = (string)$request->query('priority');
            if (in_array($priority, ['low','medium','high','urgent'], true)) {
                $q->where('priority', $priority);
            }
        }

        if ($request->filled('status')) {
            $status = $this->normalizeStatus($request->query('status'));
            $q->where('status', $status);
        }

        // Поиск по имени (только по названию задачи)
        if ($request->filled('search')) {
            $name = trim((string)$request->query('search'));
            if ($name !== '') {
                $q->where(function($qq) use ($name) {
                    $qq->where('name', 'like', "%{$name}%");
                });
            }
        }
        // Глобальный поиск по нескольким полям. Поддерживает оба параметра: 'global' и 'global_search'
        $globalTerm = $request->filled('global_search')
            ? $request->query('global_search')
            : ($request->filled('global') ? $request->query('global') : null);
        if ($globalTerm !== null) {
            $g = trim((string)$globalTerm);
            if ($g !== '') {
                $q->where(function($qq) use ($g) {
                    $qq->where('name', 'like', "%{$g}%")
                       ->orWhereHas('brand', fn($bq) => $bq->where('name', 'like', "%{$g}%"))
                       ->orWhereHas('article', fn($aq) => $aq->where('name', 'like', "%{$g}%"))
                       ->orWhereHas('type', fn($tq) => $tq->where('name', 'like', "%{$g}%"))
                       // Исполнитель (performer)
                       ->orWhereHas('assignee', fn($uq) => $uq->where('name', 'like', "%{$g}%"))
                       // Проверяющий/создатель (checker/manager)
                       ->orWhereHas('creator', fn($cq) => $cq->where('name', 'like', "%{$g}%"));
                });
            }
        }

        // Date filter: today|yesterday|date=YYYY-MM-DD
        $created = $request->query('created');
        $date = $request->query('date');
        if (in_array($created, ['today','yesterday'], true) || ($created === 'date' && $date)) {
            $today = now();
            if ($created === 'today') {
                $q->whereDate('created_at', $today->toDateString());
            } elseif ($created === 'yesterday') {
                $q->whereDate('created_at', $today->copy()->subDay()->toDateString());
            } elseif ($created === 'date' && $date) {
                $q->whereDate('created_at', $date);
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
     * Global list of all tasks with brand names and basic info.
     */
    public function all(Request $request): Response
    {
        $query = Task::query()
            ->with([
                'brand:id,name',
                'type:id,name,prefix',
                'article:id,name',
                'assignee:id,name',
                'creator:id,name',
            ])
            ->orderByDesc('created_at');

        // Role-based filtering
        $user = $request->user();
        if ($user && method_exists($user, 'hasRole')) {
            if ($user->hasRole('Administrator') || $user->hasRole('admin')) {
                // Administrator sees all tasks (no filter)
            } elseif ($user->hasRole('Manager') || $user->hasRole('manager')) {
                $query->where('created_by', $user->id);
            } elseif ($user->hasRole('Performer') || $user->hasRole('performer')) {
                $query->where('assignee_id', $user->id);
            }
        }

        $tasks = $query->get(['id','brand_id','task_type_id','article_id','name','status','priority','assignee_id','public_link','created_at']);

        // Cache static data for 1 hour to reduce database load
        $brands = cache()->remember('brands_list', 3600, function () {
            return Brand::query()->orderBy('name')->get(['id','name']);
        });

        // Assignee options - cache for 5 minutes (more dynamic due to blocking status)
        $performers = cache()->remember('performers_list', 300, function () {
            return User::role('Performer')->get(['id','name','is_blocked']);
        });
        
        $taskTypes = cache()->remember('task_types_list', 3600, function () {
            return TaskType::query()->orderBy('name')->get(['id','name','prefix']);
        });

        return Inertia::render('Tasks/All', [
            'tasks' => $tasks,
            'brands' => $brands,
            'performers' => $performers,
            'taskTypes' => $taskTypes,
        ]);
    }

    public function index(Request $request, Brand $brand): Response
    {
        $query = Task::query()
            ->where('brand_id', $brand->id)
            ->with([
                'type:id,name,prefix',
                'article:id,name',
                'assignee:id,name',
                'creator:id,name',
            ])
            ->orderByDesc('created_at');

        // Role-based filtering
        $user = $request->user();
        if ($user && method_exists($user, 'hasRole')) {
            if ($user->hasRole('Administrator') || $user->hasRole('admin')) {
                // Administrator sees all tasks (no filter)
            } elseif ($user->hasRole('Manager') || $user->hasRole('manager')) {
                $query->where('created_by', $user->id);
            } elseif ($user->hasRole('Performer') || $user->hasRole('performer')) {
                $query->where('assignee_id', $user->id);
            }
        }

        $tasks = $query->get([ 'id','brand_id','task_type_id','article_id','name','status','assignee_id','public_link','highlighted','comment','size','created_at' ]);

        // Cache static data for 5 minutes (more dynamic due to blocking status)
        $performers = cache()->remember('performers_list', 300, function () {
            return User::role('Performer')->get(['id','name','is_blocked']);
        });
        
        $taskTypes = cache()->remember('task_types_list', 3600, function () {
            return TaskType::query()->orderBy('name')->get(['id','name','prefix']);
        });

        return Inertia::render('Tasks/Index', [
            'brand' => $brand->only(['id','name']),
            'tasks' => $tasks,
            'performers' => $performers,
            'taskTypes' => $taskTypes,
        ]);
    }

    public function store(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'task_type_id' => ['required','exists:task_types,id'],
            'article_id' => ['required','exists:articles,id'],
            'name' => ['nullable','string','max:255'],
            'assignee_id' => ['nullable','exists:users,id'],
            'priority' => ['nullable','in:low,medium,high,urgent'],
            'source_files' => ['sometimes','array'],
            'source_files.*' => ['nullable','string','max:2048'],
            'source_comment' => ['nullable','string'],
        ]);

        $article = Article::findOrFail($data['article_id']);
        $type = TaskType::findOrFail($data['task_type_id']);
        $task = Task::create([
            'brand_id' => $brand->id,
            'created_by' => $request->user()->id,
            'task_type_id' => $type->id,
            'article_id' => $article->id,
            'name' => $data['name'] ?? $article->name,
            'assignee_id' => $data['assignee_id'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => isset($data['assignee_id']) ? 'assigned' : 'created',
            'source_files' => $data['source_files'] ?? null,
        ]);
        // If an initial source comment is provided, store it in task_source_comments
        if (!empty(trim((string)($data['source_comment'] ?? '')))) {
            TaskSourceComment::create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'content' => trim((string)$data['source_comment']),
                'image_path' => null,
            ]);
        }
        return back()->with('status', 'task-created');
    }

    /**
     * Create a task globally with specified brand.
     * Supports creating multiple tasks for multiple articles.
     */
    public function storeGlobal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['required','exists:brands,id'],
            'task_type_id' => ['required','exists:task_types,id'],
            'article_ids' => ['required','array','min:1'],
            'article_ids.*' => ['required','exists:articles,id'],
            'name' => ['nullable','string','max:255'],
            'assignee_id' => ['nullable','exists:users,id'],
            'priority' => ['nullable','in:low,medium,high,urgent'],
            'source_files' => ['sometimes','array'],
            'source_files.*' => ['nullable','string','max:2048'],
            'source_comment' => ['nullable','string'],
        ]);

        $brand = Brand::findOrFail($data['brand_id']);
        $type = TaskType::findOrFail($data['task_type_id']);
        
        $createdTasks = [];
        
        // Create a task for each article
        foreach ($data['article_ids'] as $articleId) {
            $article = Article::findOrFail($articleId);
            
            $task = Task::create([
                'brand_id' => $brand->id,
                'created_by' => $request->user()->id,
                'task_type_id' => $type->id,
                'article_id' => $article->id,
                'name' => $data['name'] ?? $article->name,
                'assignee_id' => $data['assignee_id'] ?? null,
                'priority' => $data['priority'] ?? 'medium',
                'status' => isset($data['assignee_id']) ? 'assigned' : 'created',
                'source_files' => $data['source_files'] ?? null,
            ]);

            // If an initial source comment is provided, store it in task_source_comments
            if (!empty(trim((string)($data['source_comment'] ?? '')))) {
                TaskSourceComment::create([
                    'task_id' => $task->id,
                    'user_id' => $request->user()->id,
                    'content' => trim((string)$data['source_comment']),
                    'image_path' => null,
                ]);
            }
            $createdTasks[] = $task;
        }

        return back()->with('status', 'task-created')->with('tasks_count', count($createdTasks));
    }

    public function update(Request $request, Brand $brand, Task $task)
    {
        abort_unless($task->brand_id === $brand->id, 404);

        // Backend enforcement: performer cannot change status of an already accepted task
        $user = $request->user();
        if (($user && method_exists($user, 'hasRole') && ($user->hasRole('Performer') || $user->hasRole('performer')))
            && $task->status === 'accepted' && $request->has('status')) {
            return response()->json(['success' => false, 'error' => 'Forbidden for performer on accepted task'], 403);
        }

        // Normalize known legacy/alias statuses before validation
        if ($request->has('status')) {
            $normalized = $this->normalizeStatus($request->string('status'));
            if ($normalized !== null) {
                $request->merge(['status' => $normalized]);
            }
        }

        $data = $request->validate([
            'name' => ['sometimes','required','string','max:255'],
            // Must match DB enum and frontend usage
            'status' => ['sometimes','required','in:created,assigned,in_progress,on_review,rework,question,rejected,accepted,cancelled,done'],
            'priority' => ['sometimes','required','in:low,medium,high,urgent'],
            'assignee_id' => ['nullable','exists:users,id'],
            // Allow changing brand as part of edit
            'brand_id' => ['sometimes','required','exists:brands,id'],
            // Allow updating type and article
            'task_type_id' => ['sometimes','required','exists:task_types,id'],
            'article_id' => ['sometimes','required','exists:articles,id'],
            'highlighted' => ['sometimes','boolean'],
            'comment' => ['nullable','string'],
            'source_files' => ['sometimes','array'],
            'source_files.*' => ['nullable','string','max:2048'],
        ]);

        // Store old status for event if needed
        $oldStatus = $task->status;

        // If assignee_id is being set and status is not explicitly provided, set status to 'assigned'
        if (array_key_exists('assignee_id', $data) && $data['assignee_id'] && !array_key_exists('status', $data)) {
            $data['status'] = 'assigned';
        }

        // Update the task
        $task->fill($data);
        // If brand_id was provided, persist it explicitly (fill already covers it, but this clarifies intent)
        if (array_key_exists('brand_id', $data)) {
            $task->brand_id = (int) $data['brand_id'];
        }

        // If article changed and name not explicitly provided, sync name with article
        if (array_key_exists('article_id', $data) && !array_key_exists('name', $data)) {
            $article = Article::find($data['article_id']);
            if ($article) {
                $task->name = $article->name;
            }
        }
        if (array_key_exists('source_files', $data)) {
            $task->source_files = $data['source_files'];
        }
        $task->save();

        // If status changed to accepted or cancelled, cleanup local thumbnails
        if (array_key_exists('status', $data)) {
            $newStatus = $task->status;
            if (in_array($newStatus, ['accepted', 'cancelled'], true) && $newStatus !== $oldStatus) {
                $this->cleanupTaskThumbnails($task);
            }
        }

        // Return JSON response for API requests, Inertia response for web
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'task' => $task->fresh()
            ]);
        }

        // For Inertia requests, return a redirect with the task data
        return back()->with([
            'status' => 'task-updated',
            'task' => $task->fresh()
        ]);
    }

    /**
     * Bulk update tasks: accepts IDs and one or more fields among status, priority, assignee_id.
     * Returns JSON for easier frontend handling.
     */
    public function bulkUpdate(Request $request)
    {
        $payload = $request->validate([
            'ids' => ['required','array','min:1'],
            'ids.*' => ['integer','exists:tasks,id'],
            'status' => ['sometimes','string'],
            'priority' => ['sometimes','string'],
            'assignee_id' => ['sometimes','nullable','integer','exists:users,id'],
        ]);

        // Backend enforcement: performer cannot change status for accepted tasks
        $user = $request->user();
        if ($user && method_exists($user, 'hasRole') && ($user->hasRole('Performer') || $user->hasRole('performer'))
            && array_key_exists('status', $payload)) {
            $acceptedExists = Task::whereIn('id', $payload['ids'])->where('status', 'accepted')->exists();
            if ($acceptedExists) {
                return response()->json(['success' => false, 'error' => 'Forbidden for performer on accepted tasks'], 403);
            }
        }

        $update = [];
        if (array_key_exists('status', $payload)) {
            $status = $this->normalizeStatus($payload['status']);
            // Must match DB enum and frontend usage
            if (!in_array($status, ['created','assigned','in_progress','on_review','rework','question','rejected','accepted','cancelled','done'], true)) {
                return response()->json(['success' => false, 'error' => 'Invalid status'], 422);
            }
            $update['status'] = $status;
        }
        if (array_key_exists('priority', $payload)) {
            $priority = $payload['priority'];
            if (!in_array($priority, ['low','medium','high','urgent'], true)) {
                return response()->json(['success' => false, 'error' => 'Invalid priority'], 422);
            }
            $update['priority'] = $priority;
        }
        if (array_key_exists('assignee_id', $payload)) {
            $update['assignee_id'] = $payload['assignee_id'];
            // If assigning, ensure status reflects assignment unless explicitly overridden
            if (!isset($update['status']) && $payload['assignee_id']) {
                $update['status'] = 'assigned';
            }
        }

        if (empty($update)) {
            return response()->json(['success' => false, 'error' => 'No fields to update'], 422);
        }

        Task::whereIn('id', $payload['ids'])->update($update);

        // If status set to accepted or cancelled, cleanup thumbnails for affected tasks
        if (array_key_exists('status', $update) && in_array($update['status'], ['accepted','cancelled'], true)) {
            $affected = Task::whereIn('id', $payload['ids'])->get(['id','brand_id','status']);
            foreach ($affected as $t) {
                if (in_array($t->status, ['accepted','cancelled'], true)) {
                    $this->cleanupTaskThumbnails($t);
                }
            }
        }

        $result = ['success' => true, 'updated' => count($payload['ids'])];
        // If the request expects JSON (API/AJAX), return JSON. Otherwise, redirect back for Inertia.
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($result);
        }
        return back()->with('status', 'tasks-bulk-updated');
    }

    /** Normalize frontend aliases to canonical statuses used in the DB. */
    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) return null;
        return match ($status) {
            // Accept both 'review' and 'on_review', persist 'on_review' to match DB enum
            'review' => 'on_review',
            // Keep 'done' as-is since DB enum includes it; some older UI sends 'done' for accepted
            // If you want to merge to 'accepted', uncomment the next line
            // 'done' => 'accepted',
            default => $status,
        };
    }

    public function destroy(Brand $brand, Task $task): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        // Remove Yandex.Disk folder (best-effort)
        $this->deleteYandexFolderStructure($brand, $task);
        // Remove local files folder
        Storage::disk('public')->deleteDirectory("tasks/{$task->id}");
        $task->delete();
        return back()->with('status', 'task-deleted');
    }

    /**
     * Bulk delete tasks (admin only via route middleware). Deletes:
     * - Task records
     * - All task comments and source comments and their image files
     * - Yandex Disk folder for each task
     * - Local storage folder tasks/{id}
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required','array','min:1'],
            'ids.*' => ['integer','exists:tasks,id'],
        ]);

        $ids = $data['ids'];
        $tasks = Task::with(['brand','type','article'])->whereIn('id', $ids)->get();
        foreach ($tasks as $task) {
            // Delete comments and their images
            $comments = TaskComment::where('task_id', $task->id)->get();
            foreach ($comments as $comment) {
                if ($comment->image_path) {
                    Storage::disk('public')->delete($comment->image_path);
                }
                $comment->delete();
            }
            $sourceComments = TaskSourceComment::where('task_id', $task->id)->get();
            foreach ($sourceComments as $comment) {
                if ($comment->image_path) {
                    Storage::disk('public')->delete($comment->image_path);
                }
                $comment->delete();
            }

            // Delete Yandex folder (best-effort)
            try { $this->deleteYandexFolderStructure($task->brand, $task); } catch (\Throwable $e) { Log::warning('bulkDelete yandex folder failed', ['task_id'=>$task->id,'e'=>$e->getMessage()]); }

            // Delete local files
            Storage::disk('public')->deleteDirectory("tasks/{$task->id}");

            // Delete task
            $task->delete();
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['success' => true, 'deleted' => count($ids)]);
        }
        return back()->with('status', 'tasks-bulk-deleted');
    }

    public function upload(Request $request, Brand $brand, Task $task): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $request->validate([
            'files.*' => ['file','max:51200'], // 50MB per file
        ]);
        $totalAdded = 0;
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("tasks/{$task->id}", 'public');
                $totalAdded += $file->getSize();
            }
        }
        if ($totalAdded > 0) {
            $task->size = ($task->size ?? 0) + $totalAdded;
            $task->save();
        }
        return back()->with('status', 'files-uploaded');
    }

    public function download(Brand $brand, Task $task)
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $disk = Storage::disk('public');
        $folder = "tasks/{$task->id}";
        if (!$disk->exists($folder)) {
            return back()->with('status', 'no-files');
        }
        // Create zip in temp
        $zipFile = tempnam(sys_get_temp_dir(), 'taskzip_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
            $files = $disk->allFiles($folder);
            foreach ($files as $file) {
                $zip->addFromString(basename($file), $disk->get($file));
            }
            $zip->close();
        }
        return response()->download($zipFile, 'task-'.$task->id.'-files.zip')->deleteFileAfterSend(true);
    }

    public function generatePublicLink(Brand $brand, Task $task): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        if (!$task->public_link) {
            $task->public_link = url('/share/tasks/'.$task->id.'/'.bin2hex(random_bytes(8)));
            $task->save();
        }
        return back()->with('status', 'public-link-created');
    }

    public function removePublicLink(Brand $brand, Task $task): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $task->public_link = null;
        $task->save();
        return back()->with('status', 'public-link-removed');
    }

    /**
     * Check whether a task with the same brand, article and type already exists (global DB check).
     * Returns JSON: { exists: boolean }
     */
    public function checkDuplicate(Request $request)
    {
        $data = $request->validate([
            'brand_id' => ['required','integer','exists:brands,id'],
            'article_id' => ['required','integer','exists:articles,id'],
            'task_type_id' => ['required','integer','exists:task_types,id'],
        ]);

        $exists = Task::query()
            ->where('brand_id', (int)$data['brand_id'])
            ->where('article_id', (int)$data['article_id'])
            ->where('task_type_id', (int)$data['task_type_id'])
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    public function updatePublicLink(Request $request, Task $task)
    {
        $request->validate(['public_link' => 'required|string']);
        $task->public_link = $request->string('public_link');
        $task->save();
        return response()->json(['success' => true, 'task' => $task]);
    }

    /**
     * Create folder structure on Yandex.Disk:
     * /{BrandName}/ф_{SubtaskNamePhotographer} and /{BrandName}/д_{SubtaskNameEditor}
     * No intermediate Task folder. Silently logs and continues on errors.
     */
    private function createYandexFolderStructure(Request $request, Brand $brand, Task $task, ?TaskType $type = null, ?Article $article = null): void
    {
        try {
            // Use a shared/global token (most recently updated) for all users
            $token = YandexToken::orderByDesc('updated_at')->first();
            if (!$token) {
                Log::warning('Yandex token not found during folder creation');
                return;
            }
            $token = $this->disk->ensureValidToken($token);

            $brandName = $this->sanitizeName($brand->name);
            $type = $type ?: $task->type; // lazy if not provided
            $article = $article ?: $task->article;
            $typeName = $this->sanitizeName(optional($type)->name ?? 'Тип');
            $articleName = $this->sanitizeName(optional($article)->name ?? $task->name);

            $brandPath = '/' . $brandName;
            $typePath = $brandPath . '/' . $typeName;
            $prefix = $type->prefix ?: mb_substr($typeName, 0, 1); // Use TaskType prefix or fallback to first char
            $leaf = $typePath . '/' . $prefix . '_' . $articleName;

            Log::info('Creating Yandex.Disk folder structure', [
                'leaf' => $leaf,
            ]);

            $this->ensureFolder($token->access_token, $brandPath);
            $this->ensureFolder($token->access_token, $typePath);
            $folderData = $this->disk->createFolderPublic($token->access_token, $leaf);

            // Store the public URL in the task
            if (isset($folderData['public_url'])) {
                $task->public_link = $folderData['public_url'];
                $task->save();
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create Yandex.Disk folders for task', [
                'task_id' => $task->id,
                'brand_id' => $brand->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** Sanitize names for Yandex paths: remove slashes and trim spaces. */
    private function sanitizeName(string $name): string
    {
        // Replace forward/back slashes and control characters
        $name = preg_replace('/[\\\n\r\t]/u', ' ', $name);
        $name = str_replace('/', '-', $name);
        return trim($name);
    }

    /** Create folder if not exists, ignore 409 Conflict errors. */
    private function ensureFolder(string $accessToken, string $path): void
    {
        try {
            $this->disk->createFolder($accessToken, $path);
        } catch (\Illuminate\Http\Client\RequestException $ex) {
            $response = $ex->response;
            $status = $response ? $response->status() : null;
            $body = $response ? Str::limit($response->body(), 500) : null;
            if ($status === 409) {
                // already exists
                return;
            }
            Log::error('Yandex.Disk createFolder failed', [
                'path' => $path,
                'status' => $status,
                'body' => $body,
            ]);
            throw $ex; // rethrow other errors
        }
    }

    /**
     * Delete the task's folder on Yandex.Disk according to the current structure rules.
     * Brand/TypePrefix_{Article}
     */
    private function deleteYandexFolderStructure(Brand $brand, Task $task): void
    {
        try {
            $token = \App\Models\YandexToken::orderByDesc('updated_at')->first();
            if (!$token) { return; }
            $token = $this->disk->ensureValidToken($token);

            $type = $task->type; // relation loaded lazily
            $article = $task->article;

            $brandName = $this->sanitizeName($brand->name);
            $typeName = $this->sanitizeName(optional($type)->name ?? 'Тип');
            $articleName = $this->sanitizeName(optional($article)->name ?? $task->name);

            $brandPath = '/' . $brandName;
            $typePath = $brandPath . '/' . $typeName;
            $prefix = $type->prefix ?: mb_substr($typeName, 0, 1); // Use TaskType prefix or fallback to first char
            $leaf = $typePath . '/' . $prefix . '_' . $articleName;

            // Best-effort delete; ignore not found errors
            try {
                $this->disk->deleteResource($token->access_token, $leaf, true);
            } catch (\Illuminate\Http\Client\RequestException $ex) {
                $status = optional($ex->response)->status();
                if ($status && in_array($status, [404, 202, 204])) {
                    // ignore not found or accepted deletions
                    return;
                }
                throw $ex;
            }
        } catch (\Throwable $e) {
            Log::error('Failed to delete Yandex.Disk folders for task', [
                'task_id' => $task->id,
                'brand_id' => $brand->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark thumbnails as accepted (for delayed cleanup after 14 days).
     * Does NOT delete files immediately - they will be cleaned up by scheduled command.
     */
    private function cleanupTaskThumbnails(Task $task): void
    {
        try {
            // Mark all thumbnails for this task with accepted_at timestamp
            // Only mark those that haven't been marked yet
            TaskFileThumbnail::where('task_id', $task->id)
                ->whereNull('accepted_at')
                ->update(['accepted_at' => now()]);
            
            Log::info('Marked thumbnails for delayed cleanup', [
                'task_id' => $task->id,
                'cleanup_after' => now()->addDays(14)->toDateTimeString()
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to mark thumbnails for cleanup', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

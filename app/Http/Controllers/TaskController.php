<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Task;
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
        // Ensure routes using this controller require auth in routes if needed.
    }
    /**
     * Global list of all tasks with brand names and basic info.
     */
    public function all(Request $request): Response
    {
        $tasks = Task::query()
            ->with([
                'brand:id,name',
                'type:id,name',
                'article:id,name',
                'assignee:id,name',
            ])
            ->orderByDesc('created_at')
            ->get(['id','brand_id','task_type_id','article_id','name','status','assignee_id','created_at']);

        $brands = Brand::query()->orderBy('name')->get(['id','name']);

        // Assignee options
        $performers = User::role('Performer')->get(['id','name','is_blocked']);
        $taskTypes = TaskType::query()->orderBy('name')->get(['id','name']);

        return Inertia::render('Tasks/All', [
            'tasks' => $tasks,
            'brands' => $brands,
            'performers' => $performers,
            'taskTypes' => $taskTypes,
        ]);
    }

    public function index(Request $request, Brand $brand): Response
    {
        $tasks = Task::query()
            ->where('brand_id', $brand->id)
            ->with([
                'type:id,name',
                'article:id,name',
                'assignee:id,name',
            ])
            ->orderByDesc('created_at')
            ->get([ 'id','brand_id','task_type_id','article_id','name','status','assignee_id','public_link','highlighted','comment','size','created_at' ]);

        $performers = User::role('Performer')->get(['id','name','is_blocked']);
        $taskTypes = TaskType::query()->orderBy('name')->get(['id','name']);

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
        ]);

        $article = Article::findOrFail($data['article_id']);
        $type = TaskType::findOrFail($data['task_type_id']);
        $task = Task::create([
            'brand_id' => $brand->id,
            'task_type_id' => $type->id,
            'article_id' => $article->id,
            'name' => $data['name'] ?? $article->name,
        ]);
        // Create Yandex.Disk folder
        $this->createYandexFolderStructure($request, $brand, $task, $type, $article);
        return back()->with('status', 'task-created');
    }

    /**
     * Create a task globally with specified brand.
     */
    public function storeGlobal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['required','exists:brands,id'],
            'task_type_id' => ['required','exists:task_types,id'],
            'article_id' => ['required','exists:articles,id'],
            'name' => ['nullable','string','max:255'],
        ]);

        $brand = Brand::findOrFail($data['brand_id']);
        $article = Article::findOrFail($data['article_id']);
        $type = TaskType::findOrFail($data['task_type_id']);

        $task = Task::create([
            'brand_id' => $brand->id,
            'task_type_id' => $type->id,
            'article_id' => $article->id,
            'name' => $data['name'] ?? $article->name,
        ]);

        $this->createYandexFolderStructure($request, $brand, $task, $type, $article);
        return back()->with('status', 'task-created');
    }

    public function update(Request $request, Brand $brand, Task $task): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $data = $request->validate([
            'name' => ['sometimes','required','string','max:255'],
            'status' => ['sometimes','required','in:created,assigned,done'],
            'assignee_id' => ['nullable','exists:users,id'],
            'highlighted' => ['sometimes','boolean'],
            'comment' => ['nullable','string'],
        ]);
        $task->fill($data)->save();
        return back()->with('status', 'task-updated');
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
            $prefix = mb_substr($typeName, 0, 1);
            $leaf = $typePath . '/' . $prefix . '_' . $articleName;

            Log::info('Creating Yandex.Disk folder structure', [
                'leaf' => $leaf,
            ]);

            $this->ensureFolder($token->access_token, $brandPath);
            $this->ensureFolder($token->access_token, $typePath);
            $this->ensureFolder($token->access_token, $leaf);
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
            $prefix = mb_substr($typeName, 0, 1);
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
}

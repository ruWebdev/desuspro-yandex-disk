<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Task;
use App\Models\User;
use App\Models\Subtask;
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
            ->with(['brand:id,name','assignee:id,name'])
            ->orderByDesc('created_at')
            ->get(['id','brand_id','name','status','ownership','assignee_id','created_at']);

        $brands = Brand::query()->orderBy('name')->get(['id','name']);

        return Inertia::render('Tasks/All', [
            'tasks' => $tasks,
            'brands' => $brands,
        ]);
    }

    public function index(Request $request, Brand $brand): Response
    {
        $tasks = Task::query()
            ->where('brand_id', $brand->id)
            ->with(['assignee:id,name'])
            ->orderByDesc('created_at')
            ->get([ 'id','brand_id','name','status','ownership','assignee_id','public_link','highlighted','comment','size','created_at' ]);

        $photographers = User::role('Photographer')->get(['id','name']);
        $editors = User::role('PhotoEditor')->get(['id','name']);

        return Inertia::render('Tasks/Index', [
            'brand' => $brand->only(['id','name']),
            'tasks' => $tasks,
            'assignees' => [
                'Photographer' => $photographers,
                'PhotoEditor' => $editors,
            ],
        ]);
    }

    public function store(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'ownership' => ['required','in:Photographer,PhotoEditor'],
        ]);
        $task = Task::create([
            'brand_id' => $brand->id,
            'name' => $data['name'],
            'ownership' => $data['ownership'],
        ]);
        // Auto-create two subtasks for Photographer and PhotoEditor
        Subtask::create([
            'task_id' => $task->id,
            'name' => 'ЗАДАНИЕ_Ф',
            'status' => 'created',
            'ownership' => 'Photographer',
        ]);
        Subtask::create([
            'task_id' => $task->id,
            'name' => 'ЗАДАНИЕ_Р',
            'status' => 'created',
            'ownership' => 'PhotoEditor',
        ]);
        // Create Yandex.Disk folders: /BrandName/TaskName/{ЗАДАНИЕ_Ф,ЗАДАНИЕ_Р}
        $this->createYandexFolderStructure($request, $brand, $task);
        return back()->with('status', 'task-created');
    }

    /**
     * Create a task globally with specified brand.
     */
    public function storeGlobal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'brand_id' => ['required','exists:brands,id'],
            'ownership' => ['nullable','in:Photographer,PhotoEditor'],
        ]);

        $task = Task::create([
            'brand_id' => $data['brand_id'],
            'name' => $data['name'],
            'ownership' => $data['ownership'] ?? 'Photographer',
        ]);

        // Auto-create two subtasks for Photographer and PhotoEditor
        Subtask::create([
            'task_id' => $task->id,
            'name' => 'ЗАДАНИЕ_Ф',
            'status' => 'created',
            'ownership' => 'Photographer',
        ]);
        Subtask::create([
            'task_id' => $task->id,
            'name' => 'ЗАДАНИЕ_Р',
            'status' => 'created',
            'ownership' => 'PhotoEditor',
        ]);
        // Create Yandex.Disk folders using the selected brand
        $brand = Brand::find($data['brand_id']);
        if ($brand) {
            $this->createYandexFolderStructure($request, $brand, $task);
        }
        return back()->with('status', 'task-created');
    }

    public function update(Request $request, Brand $brand, Task $task): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $data = $request->validate([
            'name' => ['sometimes','required','string','max:255'],
            'status' => ['sometimes','required','in:created,assigned,done'],
            'ownership' => ['sometimes','required','in:Photographer,PhotoEditor'],
            'assignee_id' => ['nullable','exists:users,id'],
            'highlighted' => ['sometimes','boolean'],
            'comment' => ['nullable','string'],
        ]);
        $originalName = $task->name;
        $task->fill($data)->save();

        // If the task name was updated, sync subtask names preserving suffix by ownership
        if (array_key_exists('name', $data) && $data['name'] && $data['name'] !== $originalName) {
            $newBase = $data['name'];
            $subtasks = Subtask::where('task_id', $task->id)->get(['id','ownership','name']);
            foreach ($subtasks as $sub) {
                $suffix = $sub->ownership === 'Photographer' ? 'Ф' : 'Р';
                $sub->name = $newBase . '_' . $suffix;
                $sub->save();
            }
        }
        return back()->with('status', 'task-updated');
    }

    public function destroy(Brand $brand, Task $task): RedirectResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        // Remove files folder
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
     * /{BrandName}/{TaskName}/ЗАДАНИЕ_Ф and /ЗАДАНИЕ_Р
     * Silently logs and continues on errors (e.g., no token, already exists).
     */
    private function createYandexFolderStructure(Request $request, Brand $brand, Task $task): void
    {
        try {
            $user = $request->user();
            if (!$user) {
                return; // not authenticated
            }
            $token = YandexToken::where('user_id', $user->id)->first();
            if (!$token) {
                Log::warning('Yandex token not found for user during folder creation', ['user_id' => $user->id]);
                return;
            }
            $token = $this->disk->ensureValidToken($token);

            $brandName = $this->sanitizeName($brand->name);
            $taskName = $this->sanitizeName($task->name);

            $brandPath = '/'.$brandName;
            $taskPath = $brandPath.'/'.$taskName;
            $subPhotographer = $taskPath.'/'.'ЗАДАНИЕ_Ф';
            $subEditor = $taskPath.'/'.'ЗАДАНИЕ_Р';

            Log::info('Creating Yandex.Disk folder structure', [
                'brandPath' => $brandPath,
                'taskPath' => $taskPath,
                'subPhotographer' => $subPhotographer,
                'subEditor' => $subEditor,
            ]);

            $this->ensureFolder($token->access_token, $brandPath);
            $this->ensureFolder($token->access_token, $taskPath);
            $this->ensureFolder($token->access_token, $subPhotographer);
            $this->ensureFolder($token->access_token, $subEditor);
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
}

<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\Manager\UserManagementController;
use App\Http\Controllers\Integrations\YandexDiskController;
use App\Http\Controllers\TaskTypeController;
use App\Http\Controllers\BrandArticleController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Brand;
use App\Models\TaskType;
use App\Models\User;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canResetPassword' => Route::has('password.request'),
        'status' => session('status'),
    ]);
});

Route::get('/dashboard', function () {
    $user = Auth::user();
    if ($user && method_exists($user, 'hasRole')) {
        if ($user->hasRole('Administrator')) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->hasRole('Manager')) {
            return redirect()->route('manager.dashboard');
        }
        if ($user->hasRole('Performer')) {
            return redirect()->route('performer.dashboard');
        }
    }
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Role-based Dashboard pages
Route::middleware(['auth', 'verified', 'role:Administrator'])
    ->get('/admin', function () {
        return Inertia::render('Admin/Dashboard', [
            'tasks' => [],
            'brands' => Brand::query()->select('id', 'name')->orderBy('name')->get(),
            'performers' => User::role('Performer')->select('id', 'name')->orderBy('name')->get(),
            'taskTypes' => TaskType::query()->select('id', 'name', 'prefix')->orderBy('name')->get(),
            'initialBrandId' => null,
        ]);
    })->name('admin.dashboard');

Route::middleware(['auth', 'verified', 'role:Manager'])
    ->get('/manager', function () {
        return Inertia::render('Manager/Dashboard', [
            'tasks' => [],
            'brands' => Brand::query()->select('id', 'name')->orderBy('name')->get(),
            'performers' => User::role('Performer')->select('id', 'name')->orderBy('name')->get(),
            'taskTypes' => TaskType::query()->select('id', 'name', 'prefix')->orderBy('name')->get(),
            'initialBrandId' => null,
        ]);
    })->name('manager.dashboard');

Route::middleware(['auth', 'verified', 'role:Performer'])
    ->get('/performer', function () {
        return Inertia::render('Performer/Dashboard', [
            'tasks' => [],
            'brands' => Brand::query()->select('id', 'name')->orderBy('name')->get(),
            'initialBrandId' => null,
        ]);
    })->name('performer.dashboard');

// Yandex authorization page (not linked in navigation)
Route::get('/authorize-yandex', function () {
    return Inertia::render('AuthorizeYandex');
})->middleware(['auth'])->name('authorize-yandex');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Yandex.Disk Integration
    Route::prefix('integrations/yandex')->name('integrations.yandex.')->group(function () {
        Route::get('/connect', [YandexDiskController::class, 'connect'])->name('connect');
        Route::get('/callback', [YandexDiskController::class, 'callback'])->name('callback');

        // Basic operations
        Route::get('/status', [YandexDiskController::class, 'status'])->name('status');
        Route::get('/disk', [YandexDiskController::class, 'diskInfo'])->name('disk');
        Route::get('/list', [YandexDiskController::class, 'list'])->name('list');
        Route::post('/create-folder', [YandexDiskController::class, 'createFolder'])->name('create_folder');
        Route::post('/move', [YandexDiskController::class, 'move'])->name('move');
        Route::delete('/delete', [YandexDiskController::class, 'delete'])->name('delete');
        Route::get('/download-url', [YandexDiskController::class, 'downloadUrl'])->name('download_url');
        Route::get('/resolve-url', [YandexDiskController::class, 'resolveUrl'])->name('resolve_url');
        Route::post('/resolve-from-item', [YandexDiskController::class, 'resolveFromItem'])->name('resolve_from_item');
        Route::post('/process-list', [YandexDiskController::class, 'processList'])->name('process_list');
        Route::post('/upload', [YandexDiskController::class, 'upload'])->name('upload');
        Route::post('/publish-folder', [YandexDiskController::class, 'publishFolder'])->name('publish_folder');
        // Temp download to public storage and cleanup
        Route::post('/download-public-to-temp', [YandexDiskController::class, 'downloadPublicToTemp'])->name('download_public_to_temp');
        Route::delete('/temp', [YandexDiskController::class, 'deleteTemp'])->name('delete_temp');
    });

    // Subtasks and comments should be available to authenticated users (Photographer/PhotoEditor/Manager)
    Route::prefix('brands/{brand}/tasks/{task}')->group(function () {
        // Subtasks
        Route::get('/subtasks', [SubtaskController::class, 'index'])->name('brands.tasks.subtasks.index');
        Route::post('/subtasks', [SubtaskController::class, 'store'])->name('brands.tasks.subtasks.store');
        Route::put('/subtasks/{subtask}', [SubtaskController::class, 'update'])->name('brands.tasks.subtasks.update');
        Route::delete('/subtasks/{subtask}', [SubtaskController::class, 'destroy'])->name('brands.tasks.subtasks.destroy');
        Route::post('/subtasks/{subtask}/public-link', [SubtaskController::class, 'generatePublicLink'])->name('brands.tasks.subtasks.public_link');
        Route::delete('/subtasks/{subtask}/public-link', [SubtaskController::class, 'removePublicLink'])->name('brands.tasks.subtasks.public_link.delete');

        // Subtask comments
        Route::get('/subtasks/{subtask}/comments', [\App\Http\Controllers\SubtaskCommentController::class, 'index'])->name('brands.tasks.subtasks.comments.index');
        Route::post('/subtasks/{subtask}/comments', [\App\Http\Controllers\SubtaskCommentController::class, 'store'])->name('brands.tasks.subtasks.comments.store');
        Route::delete('/subtasks/{subtask}/comments/{comment}', [\App\Http\Controllers\SubtaskCommentController::class, 'destroy'])->name('brands.tasks.subtasks.comments.destroy');

        // Task comments (accessible to authenticated users, including performers)
        Route::get('/comments', [\App\Http\Controllers\TaskCommentController::class, 'index'])->name('brands.tasks.comments.index');
        Route::post('/comments', [\App\Http\Controllers\TaskCommentController::class, 'store'])->name('brands.tasks.comments.store');
        Route::delete('/comments/{comment}', [\App\Http\Controllers\TaskCommentController::class, 'destroy'])->name('brands.tasks.comments.destroy');

        // Task SOURCE comments (clone of task comments but separate storage)
        Route::get('/source-comments', [\App\Http\Controllers\TaskSourceCommentController::class, 'index'])->name('brands.tasks.source_comments.index');
        Route::post('/source-comments', [\App\Http\Controllers\TaskSourceCommentController::class, 'store'])->name('brands.tasks.source_comments.store');
        Route::delete('/source-comments/{comment}', [\App\Http\Controllers\TaskSourceCommentController::class, 'destroy'])->name('brands.tasks.source_comments.destroy');
    });
});

// Role-specific entry pages (legacy removed). Future performer landing could be added here.

// Performer routes
Route::middleware(['auth', 'role:Performer'])->group(function () {
    Route::get('/my-tasks', [\App\Http\Controllers\Performer\TasksController::class, 'index'])->name('performer.tasks');
    Route::put('/performer/tasks/{task}/status', [\App\Http\Controllers\Performer\TasksController::class, 'updateStatus'])->name('performer.tasks.update_status');
    Route::get('/my-tasks/search', [\App\Http\Controllers\Performer\TasksController::class, 'search'])->name('performer.tasks.search');
});

// Users by role (manager-only)
Route::middleware(['auth', 'role:Manager|Administrator'])->group(function () {
    // All Tasks (global)
    Route::get('/tasks', [TaskController::class, 'all'])->name('tasks.all');
    Route::post('/tasks', [TaskController::class, 'storeGlobal'])->name('tasks.store');
    Route::put('/tasks/{task}/public-link', [TaskController::class, 'updatePublicLink'])->name('tasks.update_public_link');
    Route::put('/tasks/bulk-update', [TaskController::class, 'bulkUpdate'])->name('tasks.bulk_update'); // Added manager-only route for bulk task updates
    Route::get('/tasks/search', [TaskController::class, 'search'])->name('tasks.search');

    // Executors (all non-manager users) - unified management
    Route::get('/users/executors', [\App\Http\Controllers\Users\ExecutorsController::class, 'index'])->name('users.executors.index');
    Route::post('/users/executors', [\App\Http\Controllers\Users\ExecutorsController::class, 'store'])->name('users.executors.store');
    Route::put('/users/executors/{user}', [\App\Http\Controllers\Users\ExecutorsController::class, 'update'])->name('users.executors.update');
    Route::delete('/users/executors/{user}', [\App\Http\Controllers\Users\ExecutorsController::class, 'destroy'])->name('users.executors.destroy');

    // Admin Brands
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/brands', [\App\Http\Controllers\Admin\BrandController::class, 'index'])->name('brands.index');
        Route::post('/brands', [\App\Http\Controllers\Admin\BrandController::class, 'store'])->name('brands.store');
        Route::put('/brands/{brand}', [\App\Http\Controllers\Admin\BrandController::class, 'update'])->name('brands.update');

        // Yandex Disk Token Management
        Route::get('/yd_token', [\App\Http\Controllers\YandexAuthController::class, 'index'])->name('yandex.token');

        // Yandex OAuth Routes
        Route::get('/yandex/connect', [\App\Http\Controllers\YandexAuthController::class, 'connect'])
            ->name('integrations.yandex.connect');
        Route::get('/yandex/callback', [\App\Http\Controllers\YandexAuthController::class, 'callback'])
            ->name('integrations.yandex.callback');
        Route::get('/yandex/status', [\App\Http\Controllers\YandexAuthController::class, 'status'])
            ->name('integrations.yandex.status');
        Route::delete('/brands/{brand}', [\App\Http\Controllers\Admin\BrandController::class, 'destroy'])->name('brands.destroy');
    });

    // Manager Brands
    Route::prefix('manager')->name('manager.')->group(function () {
        Route::get('/brands', [\App\Http\Controllers\Manager\BrandController::class, 'index'])->name('brands.index');
        Route::post('/brands', [\App\Http\Controllers\Manager\BrandController::class, 'store'])->name('brands.store');
        Route::get('/brands/{brand}/edit', [\App\Http\Controllers\Manager\BrandController::class, 'edit'])->name('brands.edit');
        Route::put('/brands/{brand}', [\App\Http\Controllers\Manager\BrandController::class, 'update'])->name('brands.update');
    });

    // Tasks (nested under brands) - manager operations
    Route::prefix('brands/{brand}')->group(function () {
        Route::get('/tasks', [\App\Http\Controllers\TaskController::class, 'index'])->name('brands.tasks.index');
        Route::post('/tasks', [\App\Http\Controllers\TaskController::class, 'store'])->name('brands.tasks.store');
        Route::put('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->name('brands.tasks.update');
        Route::delete('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'destroy'])->name('brands.tasks.destroy');
        Route::post('/tasks/{task}/upload', [\App\Http\Controllers\TaskController::class, 'upload'])->name('brands.tasks.upload');
        Route::get('/tasks/{task}/download', [\App\Http\Controllers\TaskController::class, 'download'])->name('brands.tasks.download');
        Route::post('/tasks/{task}/public-link', [\App\Http\Controllers\TaskController::class, 'generatePublicLink'])->name('brands.tasks.public_link');
        Route::delete('/tasks/{task}/public-link', [\App\Http\Controllers\TaskController::class, 'removePublicLink'])->name('brands.tasks.public_link.delete');

        // Task comments (manager duplication removed; exposed above for all auth users)

        // Articles per brand
        Route::get('/articles', [BrandArticleController::class, 'index'])->name('brands.articles.index');
        Route::post('/articles', [BrandArticleController::class, 'store'])->name('brands.articles.store');
        Route::post('/articles/bulk-upload', [BrandArticleController::class, 'bulkUpload'])->name('brands.articles.bulk_upload');
        Route::delete('/articles/{article}', [BrandArticleController::class, 'destroy'])->name('brands.articles.destroy');
    });

    // Task Types CRUD
    Route::get('/task-types', [TaskTypeController::class, 'index'])->name('task_types.index');
    Route::post('/task-types', [TaskTypeController::class, 'store'])->name('task_types.store');
    Route::put('/task-types/{taskType}', [TaskTypeController::class, 'update'])->name('task_types.update');
    Route::delete('/task-types/{taskType}', [TaskTypeController::class, 'destroy'])->name('task_types.destroy');
});

// Manager users routes
Route::middleware(['auth', 'role:Administrator'])->prefix('users/managers')->name('users.managers.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Users\ManagersController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Users\ManagersController::class, 'store'])->name('store');
    Route::put('/{user}', [\App\Http\Controllers\Users\ManagersController::class, 'update'])->name('update');
    Route::delete('/{user}', [\App\Http\Controllers\Users\ManagersController::class, 'destroy'])->name('destroy');
    Route::post('/{user}/detach', [UserManagementController::class, 'detach'])->name('detach');
});

require __DIR__ . '/auth.php';

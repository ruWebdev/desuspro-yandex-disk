<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\Manager\UserManagementController;
use App\Http\Controllers\Users\RoleUsersController;
use App\Http\Controllers\Integrations\YandexDiskController;
use App\Http\Controllers\TaskTypeController;
use App\Http\Controllers\BrandArticleController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canResetPassword' => Route::has('password.request'),
        'status' => session('status'),
    ]);
});

Route::get('/dashboard', function () {
    $user = Auth::user();
    if ($user && method_exists($user, 'hasRole')) {
        if ($user->hasRole('Manager')) {
            return redirect()->route('tasks.all');
        }
        if ($user->hasRole('Performer')) {
            return redirect()->route('performer.tasks');
        }
    }
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
});

// Users by role (manager-only)
Route::middleware(['auth', 'role:Manager'])->group(function () {
    // All Tasks (global)
    Route::get('/tasks', [TaskController::class, 'all'])->name('tasks.all');
    Route::post('/tasks', [TaskController::class, 'storeGlobal'])->name('tasks.store');
    Route::put('/tasks/{task}/public-link', [TaskController::class, 'updatePublicLink'])->name('tasks.update_public_link');

    // Executors (all non-manager users) - unified management
    Route::get('/users/executors', [\App\Http\Controllers\Users\ExecutorsController::class, 'index'])->name('users.executors.index');
    Route::post('/users/executors', [\App\Http\Controllers\Users\ExecutorsController::class, 'store'])->name('users.executors.store');
    Route::put('/users/executors/{user}', [\App\Http\Controllers\Users\ExecutorsController::class, 'update'])->name('users.executors.update');
    Route::delete('/users/executors/{user}', [\App\Http\Controllers\Users\ExecutorsController::class, 'destroy'])->name('users.executors.destroy');

    // Brands
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
    Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

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

// Manager routes
Route::middleware(['auth', 'role:Manager'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/attach', [UserManagementController::class, 'attach'])->name('users.attach');
    Route::post('/users/{user}/detach', [UserManagementController::class, 'detach'])->name('users.detach');
});

require __DIR__.'/auth.php';

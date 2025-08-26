<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Manager\UserManagementController;
use App\Http\Controllers\Users\RoleUsersController;
use App\Http\Controllers\Integrations\YandexDiskController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canResetPassword' => Route::has('password.request'),
        'status' => session('status'),
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
        Route::delete('/delete', [YandexDiskController::class, 'delete'])->name('delete');
        Route::get('/download-url', [YandexDiskController::class, 'downloadUrl'])->name('download_url');
        Route::post('/upload', [YandexDiskController::class, 'upload'])->name('upload');
    });
});

// Users by role (manager-only)
Route::middleware(['auth', 'role:Manager'])->group(function () {
    // All Tasks (global)
    Route::get('/tasks', [TaskController::class, 'all'])->name('tasks.all');
    Route::post('/tasks', [TaskController::class, 'storeGlobal'])->name('tasks.store');

    // Photographers
    Route::get('/users/photographers', [RoleUsersController::class, 'index'])
        ->defaults('role', 'Photographer')
        ->name('users.photographers.index');
    Route::post('/users/photographers', [RoleUsersController::class, 'store'])
        ->defaults('role', 'Photographer')
        ->name('users.photographers.store');
    Route::put('/users/photographers/{user}', [RoleUsersController::class, 'update'])
        ->defaults('role', 'Photographer')
        ->name('users.photographers.update');
    Route::delete('/users/photographers/{user}', [RoleUsersController::class, 'destroy'])
        ->defaults('role', 'Photographer')
        ->name('users.photographers.destroy');

    // Photo Editors
    Route::get('/users/photo-editors', [RoleUsersController::class, 'index'])
        ->defaults('role', 'PhotoEditor')
        ->name('users.photo_editors.index');
    Route::post('/users/photo-editors', [RoleUsersController::class, 'store'])
        ->defaults('role', 'PhotoEditor')
        ->name('users.photo_editors.store');
    Route::put('/users/photo-editors/{user}', [RoleUsersController::class, 'update'])
        ->defaults('role', 'PhotoEditor')
        ->name('users.photo_editors.update');
    Route::delete('/users/photo-editors/{user}', [RoleUsersController::class, 'destroy'])
        ->defaults('role', 'PhotoEditor')
        ->name('users.photo_editors.destroy');

    // Brands
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
    Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

    // Tasks (nested under brands)
    Route::prefix('brands/{brand}')->group(function () {
        Route::get('/tasks', [\App\Http\Controllers\TaskController::class, 'index'])->name('brands.tasks.index');
        Route::post('/tasks', [\App\Http\Controllers\TaskController::class, 'store'])->name('brands.tasks.store');
        Route::put('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->name('brands.tasks.update');
        Route::delete('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'destroy'])->name('brands.tasks.destroy');
        Route::post('/tasks/{task}/upload', [\App\Http\Controllers\TaskController::class, 'upload'])->name('brands.tasks.upload');
        Route::get('/tasks/{task}/download', [\App\Http\Controllers\TaskController::class, 'download'])->name('brands.tasks.download');
        Route::post('/tasks/{task}/public-link', [\App\Http\Controllers\TaskController::class, 'generatePublicLink'])->name('brands.tasks.public_link');
        Route::delete('/tasks/{task}/public-link', [\App\Http\Controllers\TaskController::class, 'removePublicLink'])->name('brands.tasks.public_link.delete');
    });
});

// Manager routes
Route::middleware(['auth', 'role:Manager'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/attach', [UserManagementController::class, 'attach'])->name('users.attach');
    Route::post('/users/{user}/detach', [UserManagementController::class, 'detach'])->name('users.detach');
});

require __DIR__.'/auth.php';

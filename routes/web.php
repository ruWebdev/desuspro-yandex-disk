<?php

use App\Http\Controllers\ProfileController;
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
});

// Manager routes
Route::middleware(['auth', 'role:Manager'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/attach', [UserManagementController::class, 'attach'])->name('users.attach');
    Route::post('/users/{user}/detach', [UserManagementController::class, 'detach'])->name('users.detach');
});

require __DIR__.'/auth.php';

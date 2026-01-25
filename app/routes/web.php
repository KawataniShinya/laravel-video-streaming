<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\VideoController;

use App\Http\Controllers\AdminUserController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard', []);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/videos/{path?}', [VideoController::class, 'index'])->where('path', '.*')->name('videos.index');
    Route::get('/watch/{path}', [VideoController::class, 'watch'])->where('path', '.*')->name('videos.watch');
    Route::get('/stream/{path}', [VideoController::class, 'stream'])->where('path', '.*')->name('videos.stream');
    Route::get('/hls/{hash}/{file}', [VideoController::class, 'serveHls'])->name('videos.hls');
    Route::post('/videos/cache/delete', [VideoController::class, 'deleteCache'])->name('videos.cache.delete');
    Route::post('/videos/watched/toggle', [VideoController::class, 'toggleWatchStatus'])->name('videos.watched.toggle');
    Route::post('/videos/progress', [VideoController::class, 'updateProgress'])->name('videos.progress');

    // Admin User Management
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
});

require __DIR__.'/auth.php';

Route::fallback(function () {
    return redirect()->route('login');
});

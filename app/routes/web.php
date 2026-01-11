<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\VideoController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/videos/{path?}', [VideoController::class, 'index'])->where('path', '.*')->name('videos.index');
    Route::get('/watch/{path}', [VideoController::class, 'watch'])->where('path', '.*')->name('videos.watch');
    Route::get('/stream/{path}', [VideoController::class, 'stream'])->where('path', '.*')->name('videos.stream');
    Route::get('/hls/{hash}/{file}', [VideoController::class, 'serveHls'])->name('videos.hls');
});

require __DIR__.'/auth.php';

Route::fallback(function () {
    return redirect()->route('login');
});

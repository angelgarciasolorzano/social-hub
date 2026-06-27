<?php

declare(strict_types=1);

use App\Post\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('post', PostController::class)->name('post.store');
});

<?php

declare(strict_types=1);

use App\Comment\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('comments/{commentableType}/{commentableId}', [CommentController::class, 'index'])->name('comments.index');

    Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
});

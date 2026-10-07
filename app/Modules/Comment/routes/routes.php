<?php

declare(strict_types=1);

use App\Modules\Comment\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('comments/{commentType}/{commentableId}', [CommentController::class, 'index'])
        ->middleware('throttle:60,1,comments.index')
        ->name('comments.index');

    Route::post('comments', [CommentController::class, 'store'])
        ->middleware('throttle:6,1,comments.store')
        ->name('comments.store');
});

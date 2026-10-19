<?php

declare(strict_types=1);

use App\Modules\Comment\Controllers\CommentStoreController;
use App\Modules\Comment\Controllers\CommentUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('comments', CommentStoreController::class)
        ->middleware('throttle:6,1,comments.store')
        ->name('comments.store');

    Route::patch('comments/{comment}', CommentUpdateController::class)
        ->middleware('throttle:10,1,comments.update')
        ->name('comments.update');
});

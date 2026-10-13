<?php

declare(strict_types=1);

use App\Modules\Comment\Controllers\CommentStoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('comments', CommentStoreController::class)
        ->middleware('throttle:6,1,comments.store')
        ->name('comments.store');
});

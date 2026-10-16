<?php

declare(strict_types=1);

use App\Modules\Like\Controllers\LikeCommentToggleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('comments/{comment}/like', LikeCommentToggleController::class)
        ->middleware('throttle:30,1,comments.like')
        ->name('comments.like');
});

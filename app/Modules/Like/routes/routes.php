<?php

declare(strict_types=1);

use App\Modules\Like\Controllers\LikeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::controller(LikeController::class)->group(function (): void {
        Route::post('comments/{comment}/like', [LikeController::class, 'toggleComment'])
            ->middleware('throttle:30,1,comments.like')
            ->name('comments.like');

        Route::post('posts/{post}/like', [LikeController::class, 'togglePost'])
            ->middleware('throttle:30,1,posts.like')
            ->name('posts.like');
    });
});

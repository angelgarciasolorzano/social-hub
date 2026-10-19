<?php

declare(strict_types=1);

use App\Modules\Comment\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::controller(CommentController::class)
        ->prefix('comments')
        ->name('comments.')
        ->group(function (): void {
            Route::post('', [CommentController::class, 'store'])
                ->middleware('throttle:6,1,comments.store')
                ->name('store');

            Route::patch('{comment}', [CommentController::class, 'update'])
                ->middleware('throttle:10,1,comments.update')
                ->name('update');

            Route::delete('{comment}', [CommentController::class, 'destroy'])
                ->middleware('throttle:10,1,comments.destroy')
                ->name('destroy');
        });
});

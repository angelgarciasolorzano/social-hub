<?php

declare(strict_types=1);

use App\Modules\Post\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->controller(PostController::class)
    ->prefix('posts')
    ->name('posts.')
    ->group(function (): void {
        Route::get('{post}', 'show')->name('show');

        Route::post('', 'store')->name('store');
    });

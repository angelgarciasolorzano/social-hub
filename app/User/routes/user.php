<?php

declare(strict_types=1);

namespace App\User\routes;

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::resource('user', UserController::class)->only(['update', 'destroy']);

Route::controller(UserController::class)->prefix('user')->group(function (): void {
    Route::post('image/{type}', 'updateImage')->name('user.update.image');
});

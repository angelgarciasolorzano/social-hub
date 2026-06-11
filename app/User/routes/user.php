<?php

namespace App\User\routes;

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::resource('user', UserController::class)->only(['update', 'destroy']);

Route::controller(UserController::class)->prefix('user')->group(function () {
    Route::post('image/{type}', 'updateImage')->name('user.update.image');
});

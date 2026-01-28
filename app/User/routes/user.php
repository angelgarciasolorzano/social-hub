<?php

namespace App\User\routes;

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/user/image/{type}', [UserController::class, 'updateImage'])->name('user.update.image');
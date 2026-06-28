<?php

declare(strict_types=1);

use App\Auth\Register\Controllers\RegisterUserController;
use Illuminate\Support\Facades\Route;

Route::get('register', [RegisterUserController::class, 'create'])
    ->name('register');

Route::post('register', [RegisterUserController::class, 'store'])
    ->name('register.store');

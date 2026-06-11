<?php

use App\Auth\Register\Controllers\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::get('register', [RegisteredUserController::class, 'create'])
    ->name('register');

Route::post('register', [RegisteredUserController::class, 'store'])
    ->name('register.store');

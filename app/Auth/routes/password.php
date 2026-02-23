<?php

use App\Auth\Password\Controllers\NewPasswordController;
use App\Auth\Password\Controllers\PasswordController;
use App\Auth\Password\Controllers\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::put('password', PasswordController::class)
    ->middleware('throttle:6,1')
    ->name('password.update');

Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
    ->name('password.request');

Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.email');

Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

Route::post('reset-password', [NewPasswordController::class, 'store'])
    ->name('password.store');

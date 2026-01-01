<?php

use App\Auth\Password\Controllers\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
    ->name('password.request');

Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.email');

Route::get('reset-password/{token}', [PasswordResetLinkController::class, 'create'])
    ->name('password.reset');

Route::post('reset-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.update');
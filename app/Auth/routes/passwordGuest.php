<?php

declare(strict_types=1);

use App\Auth\Password\Controllers\PasswordNewController;
use App\Auth\Password\Controllers\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::controller(PasswordResetLinkController::class)->name('password.')->group(function (): void {
    Route::get('forgot-password', 'create')->name('request');
    Route::post('forgot-password', 'store')->name('email');
});

Route::controller(PasswordNewController::class)->name('password.')->group(function (): void {
    Route::get('reset-password/{token}', 'create')->name('reset');
    Route::post('reset-password', 'store')->name('store');
});

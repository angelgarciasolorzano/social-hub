<?php

use App\Auth\Password\Controllers\NewPasswordController;
use App\Auth\Password\Controllers\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::controller(PasswordResetLinkController::class)->name('password.')->group(function () {
    Route::get('forgot-password', 'create')->name('request');
    Route::post('forgot-password', 'store')->name('email');
});

Route::controller(NewPasswordController::class)->name('password.')->group(function () {
    Route::get('reset-password/{token}', 'create')->name('reset');
    Route::post('reset-password', 'store')->name('store');
});

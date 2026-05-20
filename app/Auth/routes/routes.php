<?php

use App\Auth\Login\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    require __DIR__.'/login.php';
    require __DIR__.'/register.php';
    require __DIR__.'/passwordGuest.php';
});

Route::middleware('auth')->group(function () {
    require __DIR__.'/email.php';
    require __DIR__.'/password.php';

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

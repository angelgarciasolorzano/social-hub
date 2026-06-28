<?php

declare(strict_types=1);

use App\Auth\Login\Controllers\LoginSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    require __DIR__.'/login.php';
    require __DIR__.'/register.php';
    require __DIR__.'/passwordGuest.php';
});

Route::middleware('auth')->group(function (): void {
    require __DIR__.'/email.php';
    require __DIR__.'/password.php';

    Route::post('logout', [LoginSessionController::class, 'destroy'])
        ->name('logout');
});

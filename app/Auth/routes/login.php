<?php

declare(strict_types=1);

use App\Auth\Login\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthenticatedSessionController::class, 'create'])
    ->name('login');

Route::post('login', [AuthenticatedSessionController::class, 'store'])
    ->name('login.store');

<?php

declare(strict_types=1);

use App\Auth\Modules\Login\Controllers\LoginSessionController;
use Illuminate\Support\Facades\Route;

Route::get('login', [LoginSessionController::class, 'create'])
    ->name('login');

Route::post('login', [LoginSessionController::class, 'store'])
    ->name('login.store');

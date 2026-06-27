<?php

declare(strict_types=1);

use App\Home\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/home', HomeController::class)->name('home');
});

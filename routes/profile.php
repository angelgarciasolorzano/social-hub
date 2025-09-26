<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('home')->group(function () {
        Route::get('/profile', function () {
            return Inertia::render('profile/Profile');
        })->name('profile.edit');
    });
});
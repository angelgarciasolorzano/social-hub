<?php

declare(strict_types=1);

use App\User\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::controller(ProfileController::class)->prefix('profile')->group(function (): void {
    Route::get('/', 'index')->name('profile.index');

    Route::get('/{user}', 'show')->name('profile.show');
});

Route::controller(ProfileController::class)->prefix('setting')->group(function (): void {
    Route::get('/profile', 'edit')->name('profile.edit');

    Route::patch('/profile', 'update')->name('profile.update');
});

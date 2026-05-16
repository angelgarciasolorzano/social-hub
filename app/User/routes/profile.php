<?php

use App\User\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::controller(ProfileController::class)->prefix('profile')->group(function () {
    Route::get('/', 'index')->name('profile.index');

    Route::get('/{user}', 'show')->name('profile.show');
});

Route::controller(ProfileController::class)->prefix('setting')->group(function () {
    Route::get('/profile', 'edit')->name('profile.edit');

    Route::patch('/profile', 'update')->name('profile.update');
});

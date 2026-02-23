<?php

use App\User\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'index'])->name('profile.index');

    Route::get('/{user}', [ProfileController::class, 'show'])->name('profile.show');
});

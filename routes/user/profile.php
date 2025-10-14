<?php

use App\Http\Controllers\user\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'index'])->name('profile.index');

    Route::post('/photo-profile', [ProfileController::class, 'updatedProfilePicture'])->name('profile.picture-profile-updated');

    Route::post('/cover-profile', [ProfileController::class, 'updatedCoverImage'])->name('profile.cover-profile-updated');

    Route::prefix('settings')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');

        Route::put('/', [ProfileController::class, 'update'])->name('profile.update');

        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');

        require __DIR__ . '/settings.php';
    });

    Route::get('/{user}', [ProfileController::class, 'show'])->name('profile.show');
});

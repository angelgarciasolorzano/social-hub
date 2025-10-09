<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\user\{
    PasswordController,
    TwoFactorAuthenticationController
};

Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');

Route::put('password', [PasswordController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('password.update');

Route::get('appearance', function () {
    return Inertia::render('home/profile/settings/Appearance');
})->name('appearance.edit');

Route::get('two-factor', [TwoFactorAuthenticationController::class, 'show'])->name('two-factor.show');

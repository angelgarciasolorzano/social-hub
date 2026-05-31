<?php

namespace App\User\Controllers;

use Illuminate\Support\Facades\Route;

Route::controller(SecurityController::class)->prefix('setting')->name('setting.')->group(function () {
    Route::get('security', 'editTwoFactorAuthentication')->name('security.edit.two-factor-authentication');
});

Route::controller(TwoFactorRecoveryCodeController::class)
    ->prefix('user/two-factor-recovery-codes')
    ->name('two-factor-recovery-codes.')
    ->group(function () {
        Route::post('regenerate', 'store')->name('regenerate');
    });

<?php

namespace App\User\Controllers;

use Illuminate\Support\Facades\Route;

Route::prefix('setting')->name('setting.security.')->group(function () {
    Route::controller(TwoFactorAuthenticationController::class)
        ->prefix('two-factor-authentication')
        ->name('two-factor-authentication.')
        ->group(function () {
            Route::get('', 'index')->name('index');

            Route::delete('', 'destroy')->name('destroy');

            Route::post('regenerate-recovery-codes', 'storeRecoveryCodes')->name('store-recovery-codes');
        });
});

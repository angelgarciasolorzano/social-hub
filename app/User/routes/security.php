<?php

declare(strict_types=1);

namespace App\User\Controllers;

use App\User\TwoFactor\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::prefix('setting')->name('setting.security.')->group(function (): void {
    Route::controller(TwoFactorController::class)
        ->prefix('two-factor-authentication')
        ->name('two-factor-authentication.')
        ->group(function (): void {
            Route::get('', 'index')->name('index');

            Route::delete('', 'destroy')->name('destroy');

            Route::post('regenerate-recovery-codes', 'storeRecoveryCodes')->name('store-recovery-codes');
        });
});

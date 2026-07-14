<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Controllers\TrustedDeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->name('user.')->group(function (): void {
    Route::controller(TrustedDeviceController::class)
        ->prefix('trusted-devices')
        ->name('trusted-devices.')
        ->group(function (): void {
            Route::delete('{trustedDevice}', [TrustedDeviceController::class, 'destroy'])
                ->name('destroy');

            Route::delete('', [TrustedDeviceController::class, 'destroyAll'])
                ->name('destroy-all');
        });
});

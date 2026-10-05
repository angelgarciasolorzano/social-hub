<?php

declare(strict_types=1);

use App\User\Modules\AccountSettings\Controllers\AccountSettingsController;
use App\User\Profile\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::controller(ProfileController::class)
    ->prefix('profile')
    ->name('profile.')
    ->group(function (): void {
        Route::get('', 'index')->name('index');

        Route::get('{user}', 'show')->name('show');
    });

Route::prefix('setting')->group(function (): void {
    Route::controller(AccountSettingsController::class)
        ->prefix('profile')
        ->name('profile.')
        ->group(function (): void {
            Route::get('', 'edit')->name('edit');

            Route::patch('', 'update')->name('update');
        });
});

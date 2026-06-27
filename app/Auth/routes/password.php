<?php

declare(strict_types=1);

use App\Auth\Password\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

Route::controller(PasswordController::class)->prefix('setting')->middleware('verified')->name('setting.')->group(function (): void {
    Route::get('password', 'edit')->name('password.edit');
    Route::put('password', 'update')->middleware('throttle:6,1')->name('password.update');
});

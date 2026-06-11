<?php

use App\Auth\Password\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

Route::controller(PasswordController::class)->prefix('setting')->middleware('verified')->name('setting.')->group(function () {
    Route::get('password', 'edit')->name('password.edit');
    Route::put('password', 'update')->middleware('throttle:6,1')->name('password.update');
});

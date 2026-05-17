<?php

namespace App\User\Controllers;

use Illuminate\Support\Facades\Route;

Route::controller(SecurityController::class)->prefix('setting')->name('setting.')->group(function () {
    Route::get('security', 'editTwoFactorAuthentication')->name('security.edit.two-factor-authentication');
});

<?php

use App\User\Controllers\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::controller(PreferenceController::class)->prefix('setting')->group(function () {
    Route::get('appearance', 'appearance')->name('preference.appearance');
});

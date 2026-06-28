<?php

declare(strict_types=1);

use App\User\Preferences\Controllers\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::controller(PreferenceController::class)->prefix('setting')->group(function (): void {
    Route::get('appearance', 'appearance')->name('preference.appearance');
});

<?php

declare(strict_types=1);

use App\Modules\Announcement\Controllers\AnnouncementDismissalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('announcements/{announcement}/dismiss', AnnouncementDismissalController::class)
        ->middleware('throttle:20,1,announcements.dismiss')
        ->name('announcements.dismiss');
});

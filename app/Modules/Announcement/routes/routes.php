<?php

declare(strict_types=1);

use App\Modules\Announcement\Controllers\AnnouncementDismissalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::controller(AnnouncementDismissalController::class)
        ->prefix('announcements')
        ->name('announcements.')
        ->group(function (): void {
            Route::post('{announcement}/dismiss', [AnnouncementDismissalController::class, 'dismiss'])
                ->middleware('throttle:20,1,announcements.dismiss')
                ->name('dismiss');

            Route::post('dismissals', [AnnouncementDismissalController::class, 'sync'])
                ->middleware('throttle:10,1,announcements.dismissals.sync')
                ->name('dismissals.sync');
        });
});

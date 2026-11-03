<?php

declare(strict_types=1);

use App\Modules\Notification\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->controller(NotificationController::class)
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('', 'index')->name('index');

        Route::get('recent', 'recent')->name('recent');

        Route::post('read-all', 'markAllAsRead')
            ->middleware('throttle:30,1,notifications.read-all')
            ->name('read-all');

        Route::post('{notificationId}/read', 'markAsRead')
            ->middleware('throttle:30,1,notifications.read')
            ->name('read');
    });

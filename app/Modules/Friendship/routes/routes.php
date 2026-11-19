<?php

declare(strict_types=1);

use App\Modules\Friendship\Controllers\FriendshipController;
use App\Modules\Friendship\Controllers\FriendshipIndexController;
use App\Modules\Friendship\Controllers\FriendshipSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::controller(FriendshipController::class)
        ->prefix('friendships')
        ->name('friendships.')
        ->group(function (): void {
            Route::get('/', FriendshipIndexController::class)
                ->middleware('throttle:60,1,friendships.index')
                ->name('index');

            Route::get('search', FriendshipSearchController::class)
                ->middleware('throttle:60,1,friendships.search')
                ->name('search');

            Route::post('{user}', [FriendshipController::class, 'store'])
                ->middleware('throttle:20,1,friendships.store')
                ->name('store');

            Route::post('{friendship}/accept', [FriendshipController::class, 'accept'])
                ->middleware('throttle:30,1,friendships.accept')
                ->name('accept');

            Route::delete('{friendship}/decline', [FriendshipController::class, 'decline'])
                ->middleware('throttle:30,1,friendships.decline')
                ->name('decline');

            Route::delete('{friendship}/cancel', [FriendshipController::class, 'cancel'])
                ->middleware('throttle:30,1,friendships.cancel')
                ->name('cancel');

            Route::delete('{friendship}', [FriendshipController::class, 'destroy'])
                ->middleware('throttle:30,1,friendships.destroy')
                ->name('destroy');
        });
});

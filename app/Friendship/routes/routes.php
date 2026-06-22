<?php

declare(strict_types=1);

use App\Friendship\Controllers\FriendshipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/friends/search', [FriendshipController::class, 'search'])->name('friends.search');

    Route::post('/friends/{user}/send', [FriendshipController::class, 'sendRequest'])->name('friends.send');
});

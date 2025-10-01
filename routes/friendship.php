<?php

use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/friends/search', [FriendshipController::class, 'search'])->name('friends.search');
});

<?php

use App\Post\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('post', PostController::class)->name('post.store');
});
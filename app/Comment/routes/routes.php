<?php

use App\Comment\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('comments', CommentController::class)->name('comments.store');
});

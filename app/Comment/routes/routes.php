<?php

use App\Comment\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('comments/{commentableType}/{commentableId}', [CommentController::class, 'index'])->name('comments.index');

    Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
});

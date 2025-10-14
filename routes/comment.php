<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
});

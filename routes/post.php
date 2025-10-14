<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
   Route::post('/post', [PostController::class, 'store'])->name('post.store');
});
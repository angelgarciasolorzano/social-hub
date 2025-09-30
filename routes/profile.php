<?php

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('home')->group(function () {
        Route::get('/profile', function () {
            /** @var  $posts Post */
            $posts = Post::with(['comments.user', 'comments.comments.user'])->where('user_id', Auth::id())->latest()->get();

            return Inertia::render('profile/Profile', [
                'posts' => PostResource::collection($posts),
            ]);
        })->name('profile.show');
    });
});

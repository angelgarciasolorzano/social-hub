<?php

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
}) ;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/home', function () {
        $friendsId = Auth::user()->friends()->pluck('id')->toArray();

        $posts = Post::with(['user', 'comments.user', 'comments.comments.user'])
            ->whereIn('user_id', $friendsId)
            ->latest()->get();

        return Inertia::render('home/Home', [
            'posts' => PostResource::collection($posts),
        ]);
    })->name('home');

    Route::prefix('home')->group(function () {
        require __DIR__ . '/user/profile.php';
    });
});

require __DIR__.'/auth.php';

require __DIR__.'/post.php';
require __DIR__.'/comment.php';
require __DIR__.'/friendship.php';

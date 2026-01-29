<?php

namespace App\Http\Home\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Post\Models\Post;
use App\Post\Resources\PostResource;

class HomeController extends Controller
{
    public function __invoke()
    {
        //$friendsId = Auth::user()->friends()->pluck('id')->toArray();

        $posts = Post::with(['user'])
            //->whereIn('user_id', $friendsId)
            ->latest()
            ->get();

        return Inertia::render('home/Home', [
            'user' => Auth::user(),
            'posts' => PostResource::collection($posts),
        ]);
    }
}

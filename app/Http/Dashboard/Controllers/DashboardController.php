<?php

namespace App\Http\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Post\Models\Post;
use App\Post\Resources\PostResource;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // $friendsId = Auth::user()->friends()->pluck('id')->toArray();

        $posts = Post::with(['user'])
            // ->whereIn('user_id', $friendsId)
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return Inertia::render('dashboard/Dashboard', [
            'user' => Auth::user(),
            'posts' => Inertia::scroll(fn () => PostResource::collection($posts)),
        ]);
    }
}

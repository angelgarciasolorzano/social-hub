<?php

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use App\Post\Models\Post;
use App\Post\Resources\PostResource;
use App\User\Models\User;
use App\User\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function index(): Response
    {
        $posts = Post::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return Inertia::render('profile/Profile', [
            'user' => new UserResource(Auth::user())->resolve(request()),
            'posts' => PostResource::collection($posts),
        ]);
    }

    public function show(User $user): Response
    {
        $posts = Post::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return Inertia::render('profile/Profile', [
            'user' => new UserResource($user)->resolve(request()),
            'posts' => PostResource::collection($posts),
        ]);
    }
}

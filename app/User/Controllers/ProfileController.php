<?php

declare(strict_types=1);

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
        $cursorPaginator = Post::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return Inertia::render('profile/Profile', [
            'user' => new UserResource(Auth::user())->resolve(request()),
            'posts' => Inertia::scroll(fn () => PostResource::collection($cursorPaginator)),
        ]);
    }

    public function show(User $user): Response
    {
        $cursorPaginator = Post::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return Inertia::render('profile/Profile', [
            'user' => new UserResource($user)->resolve(request()),
            'posts' => Inertia::scroll(fn () => PostResource::collection($cursorPaginator)),
        ]);
    }

    public function edit(): Response
    {
        return Inertia::render('setting/modules/profile/EditProfile', [
            'user' => new UserResource(Auth::user())->resolve(request()),
        ]);
    }
}

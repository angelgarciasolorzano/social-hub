<?php

declare(strict_types=1);

namespace App\User\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Props\CommentThreadProps;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Resources\PostResource;
use App\User\Models\User;
use App\User\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user, who is the one that sees the engagement of the listed posts.
     */
    private function viewer(): User
    {
        $viewer = Auth::user();

        abort_unless($viewer instanceof User, 401);

        return $viewer;
    }

    public function index(CommentThreadProps $commentThreadProps): Response
    {
        $cursorPaginator = Post::query()
            ->with(['user.media', 'media'])
            ->withEngagementFor($this->viewer())
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return Inertia::render('profile/Profile', [
            'user' => new UserResource(Auth::user())->resolve(request()),
            'posts' => Inertia::scroll(fn () => PostResource::collection($cursorPaginator)),
            $commentThreadProps,
        ]);
    }

    public function show(User $user, CommentThreadProps $commentThreadProps): Response
    {
        $cursorPaginator = Post::query()
            ->with(['user.media', 'media'])
            ->withEngagementFor($this->viewer())
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return Inertia::render('profile/Profile', [
            'user' => new UserResource($user)->resolve(request()),
            'posts' => Inertia::scroll(fn () => PostResource::collection($cursorPaginator)),
            $commentThreadProps,
        ]);
    }
}

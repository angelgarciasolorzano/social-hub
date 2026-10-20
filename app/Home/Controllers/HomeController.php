<?php

declare(strict_types=1);

namespace App\Home\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Props\CommentThreadProps;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Resources\PostResource;
use App\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(CommentThreadProps $commentThreadProps): Response
    {
        // $friendsId = Auth::user()->friends()->pluck('id')->toArray();

        $viewer = Auth::user();

        abort_unless($viewer instanceof User, 401);

        $cursorPaginator = Post::with(['user.media', 'media'])
            ->withEngagementFor($viewer)
            // ->whereIn('user_id', $friendsId)
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return Inertia::render('home/Home', [
            'user' => Auth::user(),
            'posts' => Inertia::scroll(fn () => PostResource::collection($cursorPaginator)),
            $commentThreadProps,
        ]);
    }
}

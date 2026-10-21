<?php

declare(strict_types=1);

namespace App\Modules\Like\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Events\LikeToggled;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Like the comment for the authenticated user, or remove the like when it already exists.
     */
    public function toggleComment(Request $request, Comment $comment): JsonResponse
    {
        return $this->toggle($request, $comment);
    }

    /**
     * Like the post for the authenticated user, or remove the like when it already exists.
     */
    public function togglePost(Request $request, Post $post): JsonResponse
    {
        return $this->toggle($request, $post);
    }

    /**
     * Toggle the like of the authenticated user and answer with the resulting state and total.
     */
    private function toggle(Request $request, Comment|Post $likeable): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return response()->json([
            'liked' => $this->toggleLike($likeable, $user),
            'likesCount' => $likeable->likes()->count(),
        ]);
    }

    /**
     * Remove the user's like when it exists or create it, returning whether the likeable ends up liked.
     */
    private function toggleLike(Comment|Post $likeable, User $user): bool
    {
        $existingLike = $likeable->likes()->whereBelongsTo($user)->first();

        if ($existingLike instanceof Like) {
            $existingLike->delete();

            event(new LikeToggled($user, $likeable, liked: false));

            return false;
        }

        try {
            $likeable->likes()->forceCreate(['user_id' => $user->id]);
        } catch (UniqueConstraintViolationException) {
            return true;
        }

        event(new LikeToggled($user, $likeable, liked: true));

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Like\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeCommentToggleController extends Controller
{
    /**
     * Like the comment for the authenticated user, or remove the like when it already exists.
     */
    public function __invoke(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $liked = $this->toggleLike($comment, $user);

        return response()->json([
            'liked' => $liked,
            'likesCount' => $comment->likes()->count(),
        ]);
    }

    /**
     * Remove the user's like when it exists or create it, returning whether the comment ends up liked.
     */
    private function toggleLike(Comment $comment, User $user): bool
    {
        $existingLike = $comment->likes()->whereBelongsTo($user)->first();

        if ($existingLike instanceof Like) {
            $existingLike->delete();

            return false;
        }

        try {
            $comment->likes()->forceCreate(['user_id' => $user->id]);
        } catch (UniqueConstraintViolationException) {
            return true;
        }

        return true;
    }
}

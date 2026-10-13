<?php

declare(strict_types=1);

namespace App\Modules\Comment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Requests\CommentStoreRequest;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CommentStoreController extends Controller
{
    public function __invoke(CommentStoreRequest $commentStoreRequest): RedirectResponse
    {
        /** @var class-string<Post | Comment> $modelType */
        $modelType = CommentType::from($commentStoreRequest->string('commentable_type')->toString())->modelClass();

        $target = $modelType::query()->findOrFail($commentStoreRequest->integer('commentable_id'));

        $thread = $target;
        $repliedToUserId = null;

        if ($target instanceof Comment && $target->commentable_type === CommentType::COMMENT) {
            $thread = Comment::query()->findOrFail($target->commentable_id);
            $repliedToUserId = $target->user_id;
        }

        $thread->comments()->create([
            'user_id' => Auth::id(),
            'replied_to_user_id' => $repliedToUserId,
            'content' => $commentStoreRequest->string('content')->toString(),
        ]);

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario publicado correctamente',
        ])->back();
    }
}

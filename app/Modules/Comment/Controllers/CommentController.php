<?php

declare(strict_types=1);

namespace App\Modules\Comment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Requests\CommentStoreRequest;
use App\Modules\Comment\Requests\CommentUpdateRequest;
use App\Modules\Post\Models\Post;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CommentController extends Controller
{
    public function store(CommentStoreRequest $commentStoreRequest): RedirectResponse
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

    #[Authorize('update', 'comment')]
    public function update(CommentUpdateRequest $commentUpdateRequest, Comment $comment): RedirectResponse
    {
        $content = $commentUpdateRequest->string('content')->toString();

        if ($comment->content !== $content) {
            $comment->update(['content' => $content, 'edited_at' => now()]);
        }

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario actualizado correctamente',
        ])->back();
    }

    #[Authorize('delete', 'comment')]
    public function destroy(Comment $comment): RedirectResponse
    {
        DB::transaction(function () use ($comment): void {
            if ($comment->comments()->exists()) {
                $comment->delete();

                return;
            }

            $this->forceDeleteWithLikes($comment);

            $this->forceDeleteEmptiedParent($comment);
        });

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario eliminado correctamente',
        ])->back();
    }

    /**
     * Delete the comment for good along with its likes, which have no foreign key to cascade.
     */
    private function forceDeleteWithLikes(Comment $comment): void
    {
        $comment->likes()->delete();

        $comment->forceDelete();
    }

    /**
     * Remove a deleted parent that just lost its last reply, so no empty tombstone is left behind.
     */
    private function forceDeleteEmptiedParent(Comment $comment): void
    {
        if ($comment->commentable_type !== CommentType::COMMENT) {
            return;
        }

        $parent = Comment::withTrashed()->find($comment->commentable_id);

        if ($parent instanceof Comment && $parent->trashed() && ! $parent->comments()->exists()) {
            $this->forceDeleteWithLikes($parent);
        }
    }
}

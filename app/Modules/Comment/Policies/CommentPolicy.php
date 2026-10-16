<?php

declare(strict_types=1);

namespace App\Modules\Comment\Policies;

use App\Modules\Comment\Models\Comment;
use App\User\Models\User;

final class CommentPolicy
{
    public function update(User $user, Comment $comment): bool
    {
        return $this->isLiveCommentOf($user, $comment);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->isLiveCommentOf($user, $comment);
    }

    /**
     * A comment can only be changed by its author, and never once it has been deleted.
     */
    private function isLiveCommentOf(User $user, Comment $comment): bool
    {
        return ! $comment->trashed() && $comment->user_id === $user->getKey();
    }
}

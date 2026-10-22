<?php

declare(strict_types=1);

namespace App\Modules\Comment\Events;

use App\Modules\Comment\Models\Comment;
use App\User\Models\User;

/**
 * Dispatched after a user comments on a post or replies inside a thread.
 * When it is a reply, `repliedToCommentId` is the comment the user answered,
 * which may be a reply that the thread flattened to its root comment.
 */
final readonly class CommentCreated
{
    public function __construct(
        public Comment $comment,
        public User $user,
        public ?int $repliedToCommentId = null,
    ) {}
}

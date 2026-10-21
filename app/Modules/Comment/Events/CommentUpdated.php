<?php

declare(strict_types=1);

namespace App\Modules\Comment\Events;

use App\Modules\Comment\Models\Comment;
use App\User\Models\User;

/**
 * Dispatched after the author changes the content of their comment.
 */
final readonly class CommentUpdated
{
    public function __construct(
        public Comment $comment,
        public User $user,
    ) {}
}

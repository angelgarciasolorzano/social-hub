<?php

declare(strict_types=1);

namespace App\Modules\Comment\Events;

use App\Modules\Comment\Models\Comment;
use App\User\Models\User;

/**
 * Dispatched after the author deletes their comment. The comment is the in-memory
 * instance, so it still carries its id after a permanent delete; `permanently`
 * is false when it became a tombstone because it has replies.
 */
final readonly class CommentDeleted
{
    public function __construct(
        public Comment $comment,
        public User $user,
        public bool $permanently,
    ) {}
}

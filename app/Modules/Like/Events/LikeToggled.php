<?php

declare(strict_types=1);

namespace App\Modules\Like\Events;

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;

/**
 * Dispatched when a user adds or removes their like; `liked` is the resulting state.
 * A toggle that changes nothing, such as a repeated like, does not dispatch it.
 */
final readonly class LikeToggled
{
    public function __construct(
        public User $user,
        public Comment|Post $likeable,
        public bool $liked,
    ) {}
}

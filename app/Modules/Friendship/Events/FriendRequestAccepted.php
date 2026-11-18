<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Events;

use App\Modules\Friendship\Models\Friendship;

/**
 * Dispatched inside the service's transaction after the receiver accepts a friend request;
 * the friendship already carries its `accepted_at`.
 */
final readonly class FriendRequestAccepted
{
    public function __construct(
        public Friendship $friendship,
    ) {}
}

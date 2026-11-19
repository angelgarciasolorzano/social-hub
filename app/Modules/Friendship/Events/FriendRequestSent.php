<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Events;

use App\Modules\Friendship\Models\Friendship;

/**
 * Dispatched inside the service's transaction after the requester sends a friend request.
 * A refused request, such as a repeated or crossed one, does not dispatch it.
 */
final readonly class FriendRequestSent
{
    public function __construct(
        public Friendship $friendship,
    ) {}
}

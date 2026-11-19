<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Events;

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\User\Models\User;
use InvalidArgumentException;

/**
 * Dispatched inside the service's transaction after a request is declined or canceled, or a
 * friendship is removed. The row is already deleted, so the event keeps only its id; `actor`
 * did it and `reason` says which of the three happened.
 */
final readonly class FriendshipEnded
{
    /**
     * @throws InvalidArgumentException when the reason does not end a friendship
     */
    public function __construct(
        public int $friendshipId,
        public User $actor,
        public User $otherUser,
        public FriendshipOperationResult $reason,
    ) {
        throw_unless(
            $reason->endsFriendship(),
            InvalidArgumentException::class,
            "A friendship cannot end with the result [{$reason->value}].",
        );
    }
}

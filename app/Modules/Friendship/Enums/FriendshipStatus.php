<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Enums;

/**
 * The stored state of a friendship row. Declining, canceling or unfriending deletes the row,
 * so there is no declined state.
 */
enum FriendshipStatus: string
{
    case Pending = 'pending';

    case Accepted = 'accepted';
}

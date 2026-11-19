<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Enums;

enum FriendshipStatus: string
{
    case Pending = 'pending';

    case Accepted = 'accepted';
}

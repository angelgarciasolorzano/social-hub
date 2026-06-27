<?php

declare(strict_types=1);

namespace App\Friendship\Enums;

enum FriendshipStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
}

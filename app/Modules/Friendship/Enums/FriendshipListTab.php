<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Enums;

enum FriendshipListTab: string
{
    case Friends = 'friends';

    case Received = 'received';

    case Sent = 'sent';
}

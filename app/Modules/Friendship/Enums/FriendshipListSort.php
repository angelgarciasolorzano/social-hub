<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Enums;

enum FriendshipListSort: string
{
    case Recent = 'recent';

    case Name = 'name';
}

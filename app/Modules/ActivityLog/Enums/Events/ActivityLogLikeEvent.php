<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Enums\Events;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;

enum ActivityLogLikeEvent: string implements ActivityLogEvent
{
    case Added = 'likes.added';

    case Removed = 'likes.removed';

    public function eventName(): string
    {
        return $this->value;
    }

    public function logName(): ActivityLogName
    {
        return ActivityLogName::Likes;
    }
}

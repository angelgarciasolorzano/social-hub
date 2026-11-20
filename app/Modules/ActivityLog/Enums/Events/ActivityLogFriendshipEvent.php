<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Enums\Events;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;

enum ActivityLogFriendshipEvent: string implements ActivityLogEvent
{
    case RequestSent = 'friendship.request_sent';

    case RequestAccepted = 'friendship.request_accepted';

    case RequestDeclined = 'friendship.request_declined';

    case RequestCanceled = 'friendship.request_canceled';

    case Removed = 'friendship.removed';

    public function eventName(): string
    {
        return $this->value;
    }

    public function logName(): ActivityLogName
    {
        return ActivityLogName::Friendships;
    }
}

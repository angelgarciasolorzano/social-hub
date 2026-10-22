<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Enums\Events;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;

enum ActivityLogAccountEvent: string implements ActivityLogEvent
{
    case Updated = 'account.updated';

    case ImageUpdated = 'account.image_updated';

    case Deleted = 'account.deleted';

    public function eventName(): string
    {
        return $this->value;
    }

    public function logName(): ActivityLogName
    {
        return ActivityLogName::Account;
    }
}

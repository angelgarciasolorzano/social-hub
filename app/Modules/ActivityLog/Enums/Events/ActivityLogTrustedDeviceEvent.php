<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Enums\Events;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;

enum ActivityLogTrustedDeviceEvent: string implements ActivityLogEvent
{
    case Created = 'trusted_devices.created';

    case Renewed = 'trusted_devices.renewed';

    case Renamed = 'trusted_devices.renamed';

    case Revoked = 'trusted_devices.revoked';

    case RevokedAll = 'trusted_devices.revoked_all';

    case Reactivated = 'trusted_devices.reactivated';

    public function eventName(): string
    {
        return $this->value;
    }

    public function logName(): ActivityLogName
    {
        return ActivityLogName::TrustedDevices;
    }
}

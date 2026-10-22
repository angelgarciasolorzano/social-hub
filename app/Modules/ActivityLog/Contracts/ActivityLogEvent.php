<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Contracts;

use App\Modules\ActivityLog\Enums\ActivityLogName;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAccountEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogLikeEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogTrustedDeviceEvent;
use BackedEnum;

/**
 * Contract shared by the per-domain activity event enums.
 * Register every new event enum in ENUMS so the catalog stays complete.
 */
interface ActivityLogEvent extends BackedEnum
{
    /**
     * @var list<class-string<ActivityLogEvent>>
     */
    public const array ENUMS = [
        ActivityLogAuthEvent::class,
        ActivityLogContentEvent::class,
        ActivityLogLikeEvent::class,
        ActivityLogTrustedDeviceEvent::class,
        ActivityLogAccountEvent::class,
        ActivityLogAdminEvent::class,
    ];

    /**
     * Name stored in the `event` column, in `domain.action` form.
     */
    public function eventName(): string;

    public function logName(): ActivityLogName;
}

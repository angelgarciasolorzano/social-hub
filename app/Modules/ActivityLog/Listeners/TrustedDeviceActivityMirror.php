<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Listeners;

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Events\TrustedDeviceEventRecorded;
use App\Modules\ActivityLog\Enums\Events\ActivityLogTrustedDeviceEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;

/**
 * Mirrors each recorded trusted device event into the `trusted_devices` log. The subject
 * is the TrustedDeviceEvent row, which keeps the device snapshot, so nothing is copied.
 */
final readonly class TrustedDeviceActivityMirror
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function handle(TrustedDeviceEventRecorded $trustedDeviceEventRecorded): void
    {
        $this->activityLogger->record(
            $this->activityLogEvent($trustedDeviceEventRecorded->trustedDeviceEvent->action),
            $trustedDeviceEventRecorded->trustedDeviceEvent,
            $trustedDeviceEventRecorded->user,
        );
    }

    private function activityLogEvent(TrustedDeviceAction $trustedDeviceAction): ActivityLogTrustedDeviceEvent
    {
        return match ($trustedDeviceAction) {
            TrustedDeviceAction::Created => ActivityLogTrustedDeviceEvent::Created,
            TrustedDeviceAction::Renewed => ActivityLogTrustedDeviceEvent::Renewed,
            TrustedDeviceAction::Renamed => ActivityLogTrustedDeviceEvent::Renamed,
            TrustedDeviceAction::Revoked => ActivityLogTrustedDeviceEvent::Revoked,
            TrustedDeviceAction::RevokedAll => ActivityLogTrustedDeviceEvent::RevokedAll,
            TrustedDeviceAction::Reactivated => ActivityLogTrustedDeviceEvent::Reactivated,
        };
    }
}

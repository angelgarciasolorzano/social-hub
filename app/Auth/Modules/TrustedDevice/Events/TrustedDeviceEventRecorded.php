<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Events;

use App\Auth\Models\TrustedDeviceEvent;
use App\User\Models\User;

/**
 * Dispatched by TrustedDeviceEvent::record() inside the caller's transaction,
 * so only real trusted device actions announce themselves; factories and seeders do not.
 */
final readonly class TrustedDeviceEventRecorded
{
    public function __construct(
        public TrustedDeviceEvent $trustedDeviceEvent,
        public User $user,
    ) {}
}

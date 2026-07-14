<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Listeners;

use App\User\Models\User;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

final readonly class TrustedDeviceInvalidate
{
    public function handle(TwoFactorAuthenticationDisabled $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $user->trustedDevices()->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Listeners;

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

final readonly class TrustedDeviceInvalidate
{
    public function handle(TwoFactorAuthenticationDisabled $twoFactorAuthenticationDisabled): void
    {
        /** @var User $user */
        $user = $twoFactorAuthenticationDisabled->user;

        DB::transaction(function () use ($user): void {
            $trustedDevices = $user->trustedDevices()->latest('last_used_at')->get();

            foreach ($trustedDevices as $trustedDevice) {
                TrustedDeviceEvent::record(
                    trustedDevice: $trustedDevice,
                    user: $user,
                    trustedDeviceAction: TrustedDeviceAction::RevokedAll,
                    request: request(),
                );
            }

            $user->trustedDevices()->delete();
        });
    }
}

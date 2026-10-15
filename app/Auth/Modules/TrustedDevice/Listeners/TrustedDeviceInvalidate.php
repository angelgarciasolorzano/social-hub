<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Listeners;

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Events\TwoFactorAuthenticationEvent;

final readonly class TrustedDeviceInvalidate
{
    public function __construct(private TrustedDeviceDashboardCache $trustedDeviceDashboardCache) {}

    /**
     * Revoke every trusted device when two-factor is confirmed or disabled,
     * so no cookie issued under a previous configuration keeps bypassing the OTP.
     */
    public function handle(TwoFactorAuthenticationEvent $twoFactorAuthenticationEvent): void
    {
        /** @var User $user */
        $user = $twoFactorAuthenticationEvent->user;

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

        $this->trustedDeviceDashboardCache->invalidate($user);
    }
}

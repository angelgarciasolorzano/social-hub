<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Listeners;

use App\Auth\Modules\TrustedDevice\Concerns\MintsTrustedDeviceToken;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

final readonly class TrustedDeviceRemember
{
    use MintsTrustedDeviceToken;

    public function __construct(private TrustedDeviceService $trustedDeviceService) {}

    /**
     * Remember the browser after a valid two-factor code when the user asked
     * for it: the service reuses the device row of the same browser (even a
     * revoked one) and returns the token for its cookie.
     */
    public function handle(ValidTwoFactorAuthenticationCodeProvided $validTwoFactorAuthenticationCodeProvided): void
    {
        /** @var Request $request */
        $request = request();

        if (! $request->boolean('remember_device')) {
            return;
        }

        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $token = $this->trustedDeviceService->rememberAfterTwoFactorChallenge(
            $validTwoFactorAuthenticationCodeProvided->user,
            $deviceDetector,
            $request,
        );

        $this->queueTrustedDeviceCookie($token);
    }
}

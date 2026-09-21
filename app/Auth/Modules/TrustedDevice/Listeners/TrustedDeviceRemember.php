<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Listeners;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Concerns\MintsTrustedDeviceToken;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use App\User\Models\User;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

final readonly class TrustedDeviceRemember
{
    use InfersDeviceMetadata;
    use MintsTrustedDeviceToken;

    public function __construct(private TrustedDeviceService $trustedDeviceService) {}

    public function handle(ValidTwoFactorAuthenticationCodeProvided $validTwoFactorAuthenticationCodeProvided): void
    {
        $user = $validTwoFactorAuthenticationCodeProvided->user;

        /** @var Request $request */
        $request = request();

        if (! $request->boolean('remember_device')) {
            return;
        }

        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $token = $this->mintToken();

        $osInfo = $this->inferOsInfo($deviceDetector);

        $userAgent = $request->userAgent();
        $ip = $request->ip();

        $trustedDevice = $this->trustedDeviceService->create(
            user: $user,
            deviceDetector: $deviceDetector,
            tokenHash: $token['hash'],
            name: null,
            userAgent: $userAgent,
            ip: $ip,
        );

        if ($userAgent !== null) {
            TrustedDevice::pruneOlder($user, $userAgent, $osInfo['name'], $ip, $trustedDevice->id);
        }

        $this->queueTrustedDeviceCookie($token['token']);

        $this->recordEvent($user, $trustedDevice, $request);
    }

    private function recordEvent(User $user, TrustedDevice $trustedDevice, Request $request): void
    {
        TrustedDeviceEvent::record(
            trustedDevice: $trustedDevice,
            user: $user,
            trustedDeviceAction: TrustedDeviceAction::Created,
            request: $request,
        );
    }
}

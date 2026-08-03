<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Listeners;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Concerns\MintsTrustedDeviceToken;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

final readonly class TrustedDeviceRemember
{
    use InfersDeviceMetadata;
    use MintsTrustedDeviceToken;

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

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('auth.trusted_devices.cookie_lifetime_minutes');

        $newDevice = $user->trustedDevices()->create([
            'name' => $this->inferDeviceName($deviceDetector),
            'token_hash' => $token['hash'],
            'user_agent' => $userAgent,
            'browser' => $this->inferBrowser($deviceDetector),
            'os_name' => $osInfo['name'],
            'os_version' => $osInfo['version'],
            'is_mobile' => $this->inferIsMobile($deviceDetector),
            'ip' => $ip,
            'last_used_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes),
        ]);

        if ($userAgent !== null) {
            TrustedDevice::pruneOlder($user, $userAgent, $osInfo['name'], $ip, $newDevice->id);
        }

        $this->queueTrustedDeviceCookie($token['token']);
    }
}

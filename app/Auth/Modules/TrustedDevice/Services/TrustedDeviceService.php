<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Services;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;

final class TrustedDeviceService
{
    use InfersDeviceMetadata;

    public function create(
        User $user,
        DeviceDetector $deviceDetector,
        string $tokenHash,
        ?string $name,
        ?string $userAgent,
        ?string $ip,
    ): TrustedDevice {
        $osInfo = $this->inferOsInfo($deviceDetector);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        $now = CarbonImmutable::now();

        return $user->trustedDevices()->create([
            'name' => $name !== null && $name !== ''
                ? $name
                : $this->inferDeviceName($deviceDetector),
            'token_hash' => $tokenHash,
            'user_agent' => $userAgent,
            'browser' => $this->inferBrowserName($deviceDetector),
            'browser_version' => $this->inferBrowserVersion($deviceDetector),
            'os_name' => $osInfo['name'],
            'os_version' => $osInfo['version'],
            'is_mobile' => $this->inferIsMobile($deviceDetector),
            'ip' => $ip,
            'last_used_at' => $now,
            'expires_at' => $now->addMinutes($cookieLifetimeMinutes),
        ]);
    }
}

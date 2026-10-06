<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Services;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class TrustedDeviceService
{
    use InfersDeviceMetadata;

    public function __construct(private TrustedDeviceDashboardCache $trustedDeviceDashboardCache) {}

    public function create(
        User $user,
        DeviceDetector $deviceDetector,
        string $tokenHash,
        ?string $name,
        ?string $userAgent,
        ?string $ip,
    ): TrustedDevice {
        $osInfo = $this->inferOsInfo($deviceDetector);

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
            'expires_at' => $now->addMinutes($this->cookieLifetimeMinutes()),
        ]);
    }

    public function rename(User $user, TrustedDevice $trustedDevice, string $name, Request $request): void
    {
        DB::transaction(function () use ($user, $trustedDevice, $name, $request): void {
            $trustedDevice->forceFill(['name' => $name])->save();

            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Renamed, $request);
        });
    }

    public function renew(User $user, TrustedDevice $trustedDevice, Request $request): void
    {
        DB::transaction(function () use ($user, $trustedDevice, $request): void {
            $trustedDevice->forceFill([
                'expires_at' => CarbonImmutable::now()->addMinutes($this->cookieLifetimeMinutes()),
            ])->save();

            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Renewed, $request);
        });
    }

    public function revoke(User $user, TrustedDevice $trustedDevice, Request $request): void
    {
        DB::transaction(function () use ($user, $trustedDevice, $request): void {
            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Revoked, $request);

            $trustedDevice->delete();
        });
    }

    public function forceDelete(User $user, TrustedDevice $trustedDevice, Request $request): void
    {
        DB::transaction(function () use ($user, $trustedDevice, $request): void {
            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Revoked, $request);

            $trustedDevice->forceDelete();
        });
    }

    /**
     * Record the audit event now and refresh the dashboard cache once the
     * surrounding transaction commits.
     */
    private function recordEvent(
        User $user,
        TrustedDevice $trustedDevice,
        TrustedDeviceAction $trustedDeviceAction,
        Request $request,
    ): void {
        TrustedDeviceEvent::record(
            trustedDevice: $trustedDevice,
            user: $user,
            trustedDeviceAction: $trustedDeviceAction,
            request: $request,
        );

        DB::afterCommit(function () use ($user): void {
            $this->trustedDeviceDashboardCache->invalidate($user);
        });
    }

    private function cookieLifetimeMinutes(): int
    {
        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        return $cookieLifetimeMinutes;
    }
}

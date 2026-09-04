<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Props;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Resources\TrustedDeviceResource;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

final readonly class CurrentTrustedDeviceProps implements ProvidesInertiaProperties
{
    use InfersDeviceMetadata;

    public function __construct(
        private Request $request,
        private DeviceDetector $deviceDetector,
        private bool $isOptional = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        $user = $this->request->user();

        $currentDevicePreview = fn (): array => $this->previewDevice();

        $currentDeviceMatch = function (): ?array {
            $match = $this->findCurrentDeviceMatch();

            return $match instanceof TrustedDevice
                ? new TrustedDeviceResource($match)->resolve($this->request)
                : null;
        };

        $trustedDevicesForRevoke = fn (): array => $this->trustedDevicesForRevoke($user);

        if ($this->isOptional) {
            return [
                'currentDevicePreview' => Inertia::optional($currentDevicePreview),
                'currentDeviceMatch' => Inertia::optional($currentDeviceMatch),
                'trustedDevicesForRevoke' => Inertia::optional($trustedDevicesForRevoke),
            ];
        }

        return [
            'currentDevicePreview' => $currentDevicePreview,
            'currentDeviceMatch' => $currentDeviceMatch,
            'trustedDevicesForRevoke' => $trustedDevicesForRevoke,
        ];
    }

    /**
     * Build a preview of the device that would be created from the current request.
     *
     * @return array{browser: string, browserVersion: string, osName: string, userAgent: string|null, isMobile: bool, lastUsedAt: string, expiresAt: string}
     */
    private function previewDevice(): array
    {
        $osInfo = $this->inferOsInfo($this->deviceDetector);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        return [
            'browser' => $this->inferBrowserName($this->deviceDetector),
            'browserVersion' => $this->inferBrowserVersion($this->deviceDetector),
            'osName' => $osInfo['name'],
            'userAgent' => $this->request->userAgent(),
            'isMobile' => $this->inferIsMobile($this->deviceDetector),
            'lastUsedAt' => CarbonImmutable::now()->toIso8601String(),
            'expiresAt' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes)->toIso8601String(),
        ];
    }

    /**
     * Find the trusted device matching the current request fingerprint, including revoked/expired.
     * Delegates to the model so the matching rules and IP-required invariant live in one place.
     */
    private function findCurrentDeviceMatch(): ?TrustedDevice
    {
        $user = $this->request->user();

        if (! $user instanceof User) {
            return null;
        }

        $userAgent = $this->request->userAgent();

        if ($userAgent === null) {
            return null;
        }

        $osInfo = $this->inferOsInfo($this->deviceDetector);

        return TrustedDevice::findAnyMatchForFingerprint($user, $userAgent, $osInfo['name'], $this->request->ip());
    }

    /**
     * Build the full, unpaginated list of the user's trusted devices as the
     * shape that RevokeAllDevicesDialog consumes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function trustedDevicesForRevoke(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $devices = $user->trustedDevices()
            ->latest('last_used_at')
            ->get()
            ->map(fn (TrustedDevice $trustedDevice): array => new TrustedDeviceResource($trustedDevice)->resolve($this->request))
            ->all();

        /** @var array<int, array<string, mixed>> $devices */
        return $devices;
    }
}

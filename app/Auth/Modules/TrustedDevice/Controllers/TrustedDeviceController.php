<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Concerns\MintsTrustedDeviceToken;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyAllRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceStoreRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceUpdateRequest;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TrustedDeviceController extends Controller
{
    use InfersDeviceMetadata;
    use MintsTrustedDeviceToken;

    public function update(TrustedDeviceUpdateRequest $trustedDeviceUpdateRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $trustedDeviceUpdateRequest->user()?->getKey(), 403);

        $trustedDevice->forceFill([
            'name' => $trustedDeviceUpdateRequest->string('name')->toString(),
        ])->save();

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo renombrado correctamente.',
        ])->back();
    }

    public function renew(Request $request, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $request->user()?->getKey(), 403);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        $trustedDevice->forceFill([
            'expires_at' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes),
        ])->save();

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Confianza renovada correctamente.',
        ])->back();
    }

    public function store(TrustedDeviceStoreRequest $trustedDeviceStoreRequest): RedirectResponse
    {
        $user = $trustedDeviceStoreRequest->user();

        abort_unless($user instanceof User, 401);

        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $osInfo = $this->inferOsInfo($deviceDetector);

        $token = $this->mintToken();

        $userAgent = $trustedDeviceStoreRequest->userAgent();
        $ip = $trustedDeviceStoreRequest->ip();

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        $newDevice = $user->trustedDevices()->create([
            'name' => $trustedDeviceStoreRequest->string('name')->toString() !== ''
                ? $trustedDeviceStoreRequest->string('name')->toString()
                : $this->inferDeviceName($deviceDetector),
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

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo agregado correctamente.',
        ])->back();
    }

    public function destroy(TrustedDeviceDestroyRequest $trustedDeviceDestroyRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $trustedDeviceDestroyRequest->user()?->getKey(), 403);

        $trustedDevice->delete();

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo de confianza revocado correctamente.',
        ])->back();
    }

    public function destroyAll(TrustedDeviceDestroyAllRequest $trustedDeviceDestroyAllRequest): RedirectResponse
    {
        $user = $trustedDeviceDestroyAllRequest->user();

        abort_unless($user instanceof User, 401);

        $user->trustedDevices()->delete();

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Todos los dispositivos de confianza fueron revocados.',
        ])->back();
    }

    /**
     * Build a preview of the device that would be created from the current request.
     *
     * @return array{browser: string, osName: string, userAgent: string|null, lastUsedAt: string, expiresAt: string}
     */
    public function inferDevicePreview(Request $request): array
    {
        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $osInfo = $this->inferOsInfo($deviceDetector);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        return [
            'browser' => $this->inferBrowser($deviceDetector),
            'osName' => $osInfo['name'],
            'userAgent' => $request->userAgent(),
            'lastUsedAt' => CarbonImmutable::now()->toIso8601String(),
            'expiresAt' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes)->toIso8601String(),
        ];
    }

    /**
     * Find the active trusted device that fingerprints as the current request
     * (user_agent + OS name + IP, with a not-yet-expired `expires_at`). Returns
     * null when the request is made from an unknown device.
     */
    public function findCurrentDeviceMatch(Request $request): ?TrustedDevice
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $userAgent = $request->userAgent();

        if ($userAgent === null) {
            return null;
        }

        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $osInfo = $this->inferOsInfo($deviceDetector);

        $builder = $user->trustedDevices()
            ->where('user_agent', $userAgent)
            ->where('os_name', $osInfo['name'])
            ->where('expires_at', '>', CarbonImmutable::now());

        $ip = $request->ip();

        if ($ip !== null) {
            $builder->where('ip', $ip);
        }

        return $builder->first();
    }
}

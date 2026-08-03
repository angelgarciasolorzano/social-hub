<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyAllRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceStoreRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceUpdateRequest;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TrustedDeviceController extends Controller
{
    /**
     * Cookie name used to identify the trusted device.
     */
    private const string COOKIE_NAME = 'trusted_device';

    /**
     * Length of the random token stored in the cookie.
     *
     * 64 chars = ~380 bits of entropy; infeasible to guess.
     */
    private const int TOKEN_LENGTH = 64;

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
        $cookieLifetimeMinutes = config('auth.trusted_devices.cookie_lifetime_minutes');

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

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('auth.trusted_devices.cookie_lifetime_minutes');

        $osInfo = $this->inferOsInfo($deviceDetector);

        $token = Str::random(self::TOKEN_LENGTH);
        $tokenHash = hash('sha256', $token);

        $userAgent = $trustedDeviceStoreRequest->userAgent();
        $ip = $trustedDeviceStoreRequest->ip();

        $trustedDevice = $user->trustedDevices()->create([
            'name' => $trustedDeviceStoreRequest->string('name')->toString() !== ''
                ? $trustedDeviceStoreRequest->string('name')->toString()
                : $this->inferDeviceName($deviceDetector),
            'token_hash' => $tokenHash,
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
            TrustedDevice::pruneOlder($user, $userAgent, $osInfo['name'], $ip, $trustedDevice->id);
        }

        Cookie::queue(Cookie::make(
            name: self::COOKIE_NAME,
            value: $token,
            minutes: $cookieLifetimeMinutes,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));

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
     * @return array{browser: string, osName: string, ip: string|null, userAgent: string|null, lastUsedAt: string}
     */
    public function inferDevicePreview(Request $request): array
    {
        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $osInfo = $this->inferOsInfo($deviceDetector);

        return [
            'browser' => $this->inferBrowser($deviceDetector),
            'osName' => $osInfo['name'],
            'ip' => $request->ip(),
            'userAgent' => $request->userAgent(),
            'lastUsedAt' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Resolve a human-friendly device name from the parsed DeviceDetector.
     */
    private function inferDeviceName(DeviceDetector $deviceDetector): string
    {
        $model = $deviceDetector->getModel();

        if ($model !== '') {
            return $model;
        }

        $os = $deviceDetector->getOs();

        if (\is_array($os)) {
            $osName = $os['name'] ?? null;

            if (\is_string($osName) && $osName !== '') {
                return match (\strtolower($osName)) {
                    'mac', 'macos', 'mac os x' => 'Mac OS',
                    'windows' => 'Windows PC',
                    'linux' => 'Linux',
                    default => $osName,
                };
            }
        }

        return $deviceDetector->getBrandName();
    }

    /**
     * Build a short browser label (e.g. "Chrome 125") from the parsed DeviceDetector.
     */
    private function inferBrowser(DeviceDetector $deviceDetector): string
    {
        $client = $deviceDetector->getClient();

        if (! \is_array($client)) {
            return '';
        }

        $name = $client['name'] ?? null;

        if (! \is_string($name) || $name === '') {
            return '';
        }

        $version = $client['version'] ?? null;

        if (\is_string($version) && $version !== '') {
            $major = explode('.', $version, 2)[0];

            if ($major !== '') {
                return \sprintf('%s %s', $name, $major);
            }
        }

        return $name;
    }

    /**
     * Resolve OS name and version from the parsed DeviceDetector.
     *
     * @return array{name: string, version: string}
     */
    private function inferOsInfo(DeviceDetector $deviceDetector): array
    {
        $os = $deviceDetector->getOs();

        if (! \is_array($os)) {
            return ['name' => '', 'version' => ''];
        }

        $name = $os['name'] ?? null;
        $version = $os['version'] ?? null;

        return [
            'name' => \is_string($name) ? $name : '',
            'version' => \is_string($version) ? $version : '',
        ];
    }

    /**
     * Resolve whether the device is a mobile or tablet (true) vs desktop/bot (false).
     */
    private function inferIsMobile(DeviceDetector $deviceDetector): bool
    {
        if ($deviceDetector->isMobile()) {
            return true;
        }

        return $deviceDetector->isTablet();
    }
}

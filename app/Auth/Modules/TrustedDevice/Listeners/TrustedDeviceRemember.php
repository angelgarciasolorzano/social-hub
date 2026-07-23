<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Listeners;

use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

final readonly class TrustedDeviceRemember
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

        $token = Str::random(self::TOKEN_LENGTH);
        $tokenHash = hash('sha256', $token);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('auth.trusted_devices.cookie_lifetime_minutes');

        $osInfo = $this->inferOsInfo($deviceDetector);

        $newDevice = $user->trustedDevices()->create([
            'name' => $this->inferDeviceName($deviceDetector),
            'token_hash' => $tokenHash,
            'user_agent' => $request->userAgent(),
            'browser' => $this->inferBrowser($deviceDetector),
            'os_name' => $osInfo['name'],
            'os_version' => $osInfo['version'],
            'is_mobile' => $this->inferIsMobile($deviceDetector),
            'ip' => $request->ip(),
            'last_used_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes),
        ]);

        $userAgent = $request->userAgent();

        if ($userAgent !== null) {
            $user->trustedDevices()
                ->where('user_agent', $userAgent)
                ->where('os_name', $osInfo['name'])
                ->where('ip', $request->ip())
                ->where('id', '!=', $newDevice->id)
                ->delete();
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

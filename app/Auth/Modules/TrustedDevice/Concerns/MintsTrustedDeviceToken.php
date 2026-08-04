<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Concerns;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

trait MintsTrustedDeviceToken
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

    /**
     * Generate a random token and its SHA-256 hash. The hash is what gets
     * persisted with the device row; the raw token is what goes into the
     * cookie.
     *
     * @return array{token: string, hash: string}
     */
    private function mintToken(): array
    {
        $token = Str::random(self::TOKEN_LENGTH);

        return [
            'token' => $token,
            'hash' => hash('sha256', $token),
        ];
    }

    /**
     * Queue the trusted-device cookie on the current response, with the
     * standard secure flags and lifetime read from config.
     */
    private function queueTrustedDeviceCookie(string $token): void
    {
        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

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
}

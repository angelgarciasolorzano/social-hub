<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Listeners;

use Carbon\CarbonImmutable;
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

    /**
     * Cookie lifetime in minutes (30 days).
     */
    private const int COOKIE_LIFETIME_MINUTES = 60 * 24 * 30;

    /**
     * Persist the trusted device and queue the cookie.
     */
    public function handle(ValidTwoFactorAuthenticationCodeProvided $validTwoFactorAuthenticationCodeProvided): void
    {
        $user = $validTwoFactorAuthenticationCodeProvided->user;

        /** @var Request $request */
        $request = request();

        if (! $request->boolean('remember_device')) {
            return;
        }

        $token = Str::random(self::TOKEN_LENGTH);
        $tokenHash = hash('sha256', $token);

        $user->trustedDevices()->create([
            'name' => null,
            'token_hash' => $tokenHash,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'last_used_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addMinutes(self::COOKIE_LIFETIME_MINUTES),
        ]);

        Cookie::queue(Cookie::make(
            name: self::COOKIE_NAME,
            value: $token,
            minutes: self::COOKIE_LIFETIME_MINUTES,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));
    }
}

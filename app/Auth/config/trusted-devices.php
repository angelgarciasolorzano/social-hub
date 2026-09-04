<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cookie lifetime
    |--------------------------------------------------------------------------
    |
    | Lifetime, in minutes, of the cookie that allows a recognised device to
    | bypass the two-factor challenge. Defaults to 30 days. Override via the
    | `TRUSTED_DEVICE_COOKIE_LIFETIME_MINUTES` environment variable when needed.
    |
    */

    'cookie_lifetime_minutes' => (int) env('SOCIALHUB_AUTH_TRUSTED_DEVICE_COOKIE_LIFETIME_MINUTES', 60 * 24 * 30),

    /*
    |--------------------------------------------------------------------------
    | Purge after days
    |--------------------------------------------------------------------------
    |
    | Days a soft-deleted trusted device row is kept in the table before the
    | `trusted-devices:purge` Artisan command (scheduled daily at 03:00 by
    | AuthServiceProvider) physically removes it. Defaults to 90 days. Override
    | via `SOCIALHUB_AUTH_TRUSTED_DEVICE_PURGE_AFTER_DAYS` when needed.
    |
    */

    'purge_after_days' => (int) env('SOCIALHUB_AUTH_TRUSTED_DEVICE_PURGE_AFTER_DAYS', 90),
];

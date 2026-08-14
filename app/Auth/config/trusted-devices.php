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
    | Retention grace days
    |--------------------------------------------------------------------------
    |
    | Days after a trusted device's `expires_at` that the scheduled cleanup
    | task keeps its row around for audit/revoke purposes before deleting.
    | Defaults to 30 days. Override via the
    | `TRUSTED_DEVICE_RETENTION_GRACE_DAYS` environment variable when needed.
    |
    */

    'retention_grace_days' => (int) env('SOCIALHUB_AUTH_TRUSTED_DEVICE_RETENTION_GRACE_DAYS', 30),
];

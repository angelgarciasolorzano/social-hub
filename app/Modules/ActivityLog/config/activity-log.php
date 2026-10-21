<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Retention days per log
    |--------------------------------------------------------------------------
    |
    | Days each activity log keeps its records before the `activity-log:purge`
    | Artisan command deletes them. Keys match the `ActivityLogName` cases.
    | Likes are high volume and short-lived; the admin audit is kept longest.
    | Override each value via `SOCIALHUB_ACTIVITY_LOG_<LOG>_RETENTION_DAYS`.
    |
    */

    'retention_days' => [
        'auth' => (int) env('SOCIALHUB_ACTIVITY_LOG_AUTH_RETENTION_DAYS', 180),
        'content' => (int) env('SOCIALHUB_ACTIVITY_LOG_CONTENT_RETENTION_DAYS', 365),
        'likes' => (int) env('SOCIALHUB_ACTIVITY_LOG_LIKES_RETENTION_DAYS', 30),
        'trusted_devices' => (int) env('SOCIALHUB_ACTIVITY_LOG_TRUSTED_DEVICES_RETENTION_DAYS', 180),
        'account' => (int) env('SOCIALHUB_ACTIVITY_LOG_ACCOUNT_RETENTION_DAYS', 365),
        'admin' => (int) env('SOCIALHUB_ACTIVITY_LOG_ADMIN_RETENTION_DAYS', 730),
    ],
];

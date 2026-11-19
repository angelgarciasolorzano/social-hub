<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cooldown after a declined request
    |--------------------------------------------------------------------------
    |
    | Days during which someone whose friend request was declined cannot send
    | another one to the person who declined it. The person who declined may
    | still send a request. Canceling or unfriending starts no cooldown.
    | Override via `SOCIALHUB_FRIENDSHIP_DECLINE_COOLDOWN_DAYS`.
    |
    */

    'decline_cooldown_days' => (int) env('SOCIALHUB_FRIENDSHIP_DECLINE_COOLDOWN_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Pending requests a user may send
    |--------------------------------------------------------------------------
    |
    | Maximum number of sent requests still waiting for an answer, so a single
    | account cannot flood others with requests. Override via
    | `SOCIALHUB_FRIENDSHIP_MAX_PENDING_SENT`.
    |
    */

    'max_pending_sent' => (int) env('SOCIALHUB_FRIENDSHIP_MAX_PENDING_SENT', 50),

    /*
    |--------------------------------------------------------------------------
    | User search page size
    |--------------------------------------------------------------------------
    |
    | Number of people each page of the friend search returns.
    |
    */

    'search_page_size' => 15,
];

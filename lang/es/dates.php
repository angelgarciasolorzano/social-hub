<?php

declare(strict_types=1);

/**
 * Date texts of the React application, read by `shared/lib/dateTime.ts` through `useDateTime()`.
 * The formats are dayjs format strings, so literal words go between brackets.
 */
return [
    'formats' => [
        'long' => 'D [de] MMMM [del] YYYY, h:mm A',
        'short' => 'D MMM YYYY',
        'full' => 'D [de] MMMM [del] YYYY',
        'activation_date' => 'D [de] MMMM [de] YYYY',
        'activation_time' => 'h:mm A',
    ],

    'time_with_offset' => ':time (GMT:offset)',
    'never' => 'Nunca',
    'never_lowercase' => 'nunca',
    'not_available' => 'No disponible',
    'expired' => 'vencido',
];

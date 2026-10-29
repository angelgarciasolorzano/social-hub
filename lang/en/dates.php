<?php

declare(strict_types=1);

/**
 * Date texts of the React application, read by `shared/lib/dateTime.ts` through `useDateTime()`.
 * The formats are dayjs format strings, so literal words go between brackets.
 */
return [
    'formats' => [
        'long' => 'MMMM D, YYYY [at] h:mm A',
        'short' => 'MMM D, YYYY',
        'full' => 'MMMM D, YYYY',
        'activation_date' => 'MMMM D, YYYY',
        'activation_time' => 'h:mm A',
    ],

    'time_with_offset' => ':time (GMT:offset)',
    'never' => 'Never',
    'never_lowercase' => 'never',
    'not_available' => 'Not available',
    'expired' => 'expired',
];

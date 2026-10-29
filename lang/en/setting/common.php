<?php

declare(strict_types=1);

/**
 * Texts shared by several settings pages of the React application: the sidebar, the buttons every
 * dialog repeats and the two-factor code fields. A text used by one page only lives in that page's
 * file (`setting/account-settings`, `setting/two-factor`, `setting/trusted-devices`).
 */
return [
    'sidebar' => [
        'group_label' => 'Settings',
        'account' => 'Account',
        'profile' => 'Profile',
        'preferences' => 'Preferences',
        'appearance' => 'Appearance',
        'security' => 'Security',
        'two_factor' => 'Two-factor authentication',
        'trusted_devices' => 'Trusted devices',
    ],

    'actions' => [
        'cancel' => 'Cancel',
        'close' => 'Close',
        'retry' => 'Retry',
        'retrying' => 'Retrying...',
    ],

    'two_factor_code' => [
        'otp_label' => 'Verification code',
        'recovery_label' => 'Recovery code',
        'use_recovery_code' => 'Use a recovery code',
        'use_authenticator_code' => 'Use a code from my authenticator app',
        'recovery_single_use' => 'Each recovery code can only be used once.',
    ],
];

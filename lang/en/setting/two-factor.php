<?php

declare(strict_types=1);

/**
 * Texts of the two-factor authentication settings page (`setting/modules/twoFactor`), its setup wizard
 * and dialogs, and the flashes and validation messages of its controller and requests. Shared buttons
 * such as Cancel and the code fields live in `setting/common`.
 */
return [
    'page_title' => 'Two-factor authentication',

    'labels' => [
        'backup_codes' => 'Backup codes',
        'trusted_devices' => 'Trusted devices',
        'activation_date' => 'Activation date',
        'method_totp' => 'TOTP (App)',
        'understood' => 'Got it',
        'back' => 'Back',
        'continue' => 'Continue',
        'confirm' => 'Confirm',
        'download_txt' => 'Download .txt',
        'password' => 'Password',
        'regenerate_codes' => 'Regenerate codes',
        'disable' => 'Disable 2FA',
        'loading_backup_codes' => 'Loading backup codes.',
        'no_backup_codes' => 'No backup codes available.',
    ],

    'disabled_view' => [
        'title' => 'Two-factor authentication (2FA)',
        'description' => 'Add an extra layer of security to your account. With 2FA, besides your password, you will be asked for a one-time code to verify your identity.',
        'status' => 'Disabled',
        'continue_setup' => 'Continue setup',
        'activate' => 'Enable 2FA',
        'activating' => 'Enabling...',
        'activate_failed' => 'Could not enable 2FA. Please try again.',
        'pin_title' => 'This PIN will be requested every time you sign in.',
        'pin_description' => 'You can get it from a TOTP-compatible app on your phone.',
        'questions' => 'Have questions?',
        'faq_link' => 'Read our FAQ',
        'security_priority' => 'Your security is our priority.',
        'benefits_title' => 'Benefits of enabling 2FA',
        'important_details_title' => 'Important details',
        'how_it_works' => 'How it works',
        'requirements_title' => 'Requirements',
        'recommended_apps' => 'Recommended apps',
    ],

    'benefits' => [
        'security' => [
            'title' => 'More security',
            'description' => 'Protect your account against unauthorized access.',
        ],
        'verification' => [
            'title' => 'Extra verification',
            'description' => 'Requires a one-time code in addition to your password.',
        ],
        'standard' => [
            'title' => 'Trusted standard',
            'description' => 'We use TOTP, a widely recognized and secure standard.',
        ],
    ],

    'important_details' => [
        'standard' => [
            'title' => 'Trusted standard',
            'description' => 'Save the backup codes we will give you. They will let you access your account if you lose your phone.',
        ],
        'backup_codes' => [
            'title' => 'Backup codes',
            'description' => 'Each recovery code can be used once to access your account and is removed after use. If you need more, generate new codes after enabling 2FA.',
        ],
        'trusted_devices' => [
            'title' => 'Trusted devices',
            'description' => 'Mark your devices as trusted so you do not have to enter a verification code every time you sign in from them.',
        ],
        'support' => [
            'title' => 'Having trouble?',
            'description' => 'If you cannot access your account, use a backup code or contact support for help.',
        ],
    ],

    'operation_steps' => [
        'scan' => [
            'title' => 'Scan the QR code',
            'description' => 'Open your authenticator app (Google Authenticator, Authy, 1Password, etc.) and scan the code.',
        ],
        'get_code' => [
            'title' => 'Get your code',
            'description' => 'The app will generate 6-digit codes that change every 30 seconds.',
        ],
        'enter_code' => [
            'title' => 'Enter the code',
            'description' => 'Enter the 6-digit code to verify and enable 2FA on your account.',
        ],
    ],

    'requirements' => [
        'totp_app' => 'A TOTP-compatible authenticator app',
        'mobile_access' => 'Access to your mobile phone',
        'internet_connection' => 'An internet connection (for the initial setup)',
    ],

    'enabled_view' => [
        'security_options' => 'Security options',
        'active_title' => '2FA is active',
        'active_description' => 'Your account is protected with two-factor authentication.',
        'method' => 'Method:',
        'manage' => 'Manage',
        'manage_menu' => '2FA actions',
        'summary_title' => 'Security summary',
        'safety_tips_title' => 'Security tips',
        'view_details' => 'View details',
    ],

    'summary' => [
        'status_title' => '2FA status',
        'status_description' => 'Two-factor authentication is active on your account.',
        'status_active' => 'Enabled',
        'trusted_devices_count' => '{1} :count trusted device set up.|[0,*] :count trusted devices set up.',
        'configure' => 'Set up',
        'view_devices' => 'View devices',
        'backup_codes_available' => 'You have :available of :total codes available.',
        'not_activated' => 'You have not enabled two-factor authentication yet.',
        'activated_on' => 'You enabled 2FA on :date',
    ],

    'security_options' => [
        'backup_codes' => [
            'title' => 'Backup codes',
            'description' => 'Use these codes if you cannot access your authenticator.',
        ],
        'regenerate_codes' => [
            'title' => 'Regenerate codes',
            'description' => 'Generate new backup codes. The current ones will stop working.',
        ],
        'disable' => [
            'title' => 'Disable 2FA',
            'description' => 'Turn off two-factor authentication on your account.',
        ],
    ],

    'safety_tips' => [
        'save_backup_codes' => [
            'title' => 'Save your codes',
            'description' => 'Store your backup codes in a safe place that you can reach.',
        ],
        'use_trusted_devices' => [
            'title' => 'Use trusted devices',
            'description' => 'Mark devices as trusted to avoid frequent verifications.',
        ],
        'keep_app_updated' => [
            'title' => 'Keep your app up to date',
            'description' => 'Make sure you have the latest version of your authenticator app for the best security.',
        ],
    ],

    'manage_actions' => [
        'view_codes' => 'View backup codes',
        'regenerate_codes' => 'Regenerate codes',
        'trusted_devices' => 'Trusted devices',
        'disable' => 'Disable 2FA',
    ],

    'setup_steps' => [
        'qr' => [
            'title' => 'Enable two-factor authentication',
            'description' => 'Scan the QR code with your authenticator app.',
        ],
        'manual' => [
            'title' => 'Cannot scan the QR code',
            'description' => 'Enter this key manually in your authenticator app.',
        ],
        'verify' => [
            'title' => 'Verify authentication code',
            'description' => 'Enter the 6-digit code shown by your app to confirm that it works correctly.',
        ],
        'success' => [
            'title' => '2FA enabled successfully!',
            'description' => 'Save these backup codes in a safe place.',
        ],
    ],

    'choose_method' => [
        'qr_alt' => 'QR code to set up two-factor authentication',
        'qr_ready' => 'QR code ready to scan.',
        'qr_loading' => 'Loading QR code.',
        'cannot_scan' => 'Cannot scan the code?',
        'tip_title' => 'Tip',
        'tip_description' => 'Open your app and use the option to add a new account.',
    ],

    'manual_setup' => [
        'title' => 'Manual code',
        'instructions' => 'In your app, select "Enter a setup key" or "Add account manually" and paste this key.',
        'copy' => 'Copy manual code',
        'loading' => 'Loading manual code.',
        'copied_title' => 'Key copied to clipboard',
        'copied_countdown' => '{1} This message will disappear automatically in :count second.|[0,*] This message will disappear automatically in :count seconds.',
        'time_left' => 'Time left',
    ],

    'verify_otp' => [
        'hint' => 'The code changes every 30 seconds in your app.',
        'submitting' => 'Verifying…',
    ],

    'success_step' => [
        'load_failed_title' => 'We could not show your backup codes.',
        'load_failed_description' => 'Try again in a few seconds to load them again.',
        'ready' => 'Backup codes ready.',
        'important' => 'Important',
        'single_use' => 'Each code can only be used once. Save or download these codes now.',
    ],

    'error_alert' => [
        'title' => 'Something went wrong',
        'description' => 'We could not load the information. Please try again.',
    ],

    'fetch_errors' => [
        'qr_code' => 'Could not load the QR code.',
        'setup_key' => 'Could not load the setup key.',
        'recovery_codes' => 'Could not load the recovery codes.',
    ],

    'disable_dialog' => [
        'title' => 'Disable two-factor authentication',
        'description' => 'For security, we need to verify your identity before disabling two-factor authentication. Enter the code from your authenticator app or one of your recovery codes.',
        'warning_title' => 'Are you sure you want to disable two-factor authentication?',
        'warning_description' => 'Disabling 2FA will make your account more vulnerable. You should only do it if you have a specific reason.',
        'submitting' => 'Disabling 2FA...',
    ],

    'regenerate_dialog' => [
        'title' => 'Regenerate backup codes',
        'description' => 'Are you sure you want to regenerate your backup codes? This action cannot be undone.',
        'warning_title' => 'Your current codes will stop working.',
        'warning_description' => 'Once you generate new codes, the previous ones can no longer be used.',
        'password_prompt' => 'To continue, enter your password',
        'submitting' => 'Regenerating...',
    ],

    'activation_details' => [
        'description' => 'Here you can see when you enabled two-factor authentication (2FA) on your account.',
        'time' => 'Time',
        'method' => 'Method',
        'protect_title' => 'Protect your account',
        'protect_description' => 'If you do not recognize this activity, we recommend changing your password and reviewing your trusted devices.',
        'go_to_devices' => 'Go to trusted devices',
        'go_to_devices_hint' => 'Takes you to the trusted devices section.',
    ],

    'devices_panel' => [
        'description' => 'Trusted devices reduce how often you are asked for the verification code.',
        'revoke_hint' => 'Revoke any device you no longer use to keep your account secure.',
        'registered' => 'Registered devices',
        'add' => 'Add device',
        'empty' => 'You have no active trusted devices.',
        'show_all' => 'Show all devices',
        'revoke_all' => 'Revoke all',
        'tips_title' => 'Tips',
        'tips' => [
            'only_yours' => 'Use devices that are only yours.',
            'sign_out' => 'Sign out of devices you no longer use.',
            'delay' => 'Changes may take a few minutes to show up.',
        ],
        'load_failed_title' => 'Could not load the trusted devices',
        'load_failed_description' => 'Check your connection and try again.',
        'loading' => 'Loading trusted devices.',
        'last_used' => 'Last used: :time',
    ],

    'recovery_codes' => [
        'load_failed' => 'Could not load the backup codes.',
        'save_title' => 'Save these codes in a safe place.',
        'save_description' => 'They will let you access your account if you lose access to your authenticator app.',
        'retry_hint' => 'Try loading the backup codes again.',
        'load' => 'Load codes',
        'available' => 'Available codes',
        'available_count' => ':available of :total',
        'copied' => 'Copied',
        'copy_all' => 'Copy codes',
        'last_regenerated' => 'Last regenerated',
        'code_copied' => 'Backup code :code copied',
        'copy_code' => 'Copy backup code :code',
    ],

    'download' => [
        'title' => 'Two-factor authentication backup codes',
        'account' => 'Account: :email',
        'generated' => 'Generated: :date',
        'notice' => 'Keep them in a safe place. Each code can only be used once.',
    ],

    'messages' => [
        'recovery_codes_regenerated' => 'Recovery codes regenerated successfully.',
        'disabled' => 'Two-factor authentication has been disabled successfully.',
    ],

    'validation' => [
        'password_required' => 'You must confirm your password.',
        'password_mismatch' => 'The password does not match your current password.',
        'password_incorrect' => 'The password is incorrect.',
        'code_required' => 'You must enter the authentication code.',
        'code_too_long' => 'The code is too long.',
        'code_format' => 'Enter a 6-digit code or a valid recovery code.',
        'code_invalid' => 'The authentication code is not valid.',
    ],
];

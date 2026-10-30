<?php

declare(strict_types=1);

/**
 * Texts of the trusted device dialogs (`trustedDevice/components/dialog/`) and of the activity dialog's
 * pieces (`components/table/trustedDeviceActivityDialog/`). Labels the page already shows (statuses,
 * columns, filters, pagination) are reused from `setting/trusted-devices`, and Cancel and Close from
 * `setting/common`. A text split in `_before`/`_after` wraps an emphasized part rendered by the dialog.
 */
return [
    'this_device' => 'this device',

    'password_confirmation' => [
        'prompt' => 'To continue, confirm by entering your password',
        'placeholder' => 'Enter your password',
    ],

    'add' => [
        'title' => 'Add trusted device',
        'description' => 'This browser is not registered as a trusted device yet. Once added, you will not be asked for the verification code from this browser until it expires. It is a temporary convenience to skip that code, not an independent second authentication factor.',
        'personal_only' => 'Only add it if it is your personal computer. You can revoke it anytime from this screen.',
        'submit' => 'Add device',
        'submitting' => 'Adding...',
        'go_to_two_factor' => 'Go to two-factor authentication',
        'detected_device' => 'Detected device',
        'unregistered' => 'Not registered',
        'device_description' => ':browser on :os',
        'name_label' => 'Device name (optional)',
        'name_placeholder' => 'My device',
        'two_factor_pending_title' => 'Two-factor authentication is not confirmed yet',
        'two_factor_pending_description' => 'You already started the setup, but you have not entered the code from your authenticator app yet. Confirm it to be able to add trusted devices.',
        'two_factor_disabled_title' => 'Enable two-factor authentication first',
        'two_factor_disabled_description' => 'To add a trusted device you need two-factor authentication enabled and confirmed. Enable it from the security settings.',
    ],

    'already_registered' => [
        'title' => 'Device already registered',
        'description' => 'This device is already registered as trusted on your account. There is no need to add it again.',
        'changes_title' => 'Need to make changes?',
        'changes_description' => 'You can manage this device from the list of trusted devices.',
        'go_to_devices' => 'Go to trusted devices',
    ],

    'details' => [
        'title' => 'Device details',
        'description_revoked' => 'View the information of the device that was revoked from your account.',
        'description_expired' => 'View the information of a device whose trust has expired.',
        'description_active' => 'View the full information of this trusted device.',
        'information' => 'Device information',
        'state_revoked' => 'It is no longer trusted, even though its expiration date has not passed. The verification code will be requested on your next sign-in.',
        'state_expired' => 'The trust period expired. The verification code will be requested on your next sign-in from this device.',
        'state_active' => 'This device is still trusted. You will not be asked for a verification code when signing in from here until it expires.',
        'registered' => 'Registered',
    ],

    'expired' => [
        'title' => 'Trust expired',
        'description' => 'This device was already registered but its trust expired. To trust it again, renew its trust from the list of expired devices.',
        'expired_on' => 'Expired on',
        'go_to_expired' => 'Go to expired devices',
        'trust_again_title' => 'Do you want to trust this device again?',
        'trust_again_description' => 'Renew the trust to extend the expiration date without registering it again.',
    ],

    'force_destroy' => [
        'title' => 'Delete permanently',
        'description' => 'You will permanently delete :device from your account. This action is final and you will not be able to trust it again.',
        'submit' => 'Delete permanently',
        'submitting' => 'Deleting...',
        'consequences_title' => 'Consequences of permanent deletion',
        'consequences' => [
            'deleted' => 'This device will be permanently deleted from the database.',
            'no_reactivation' => 'You will no longer be able to reactivate it from the "Revoked" section.',
            'history' => 'Your activity history will still show the actions taken with this device, but you will no longer be able to open them to see the details.',
        ],
        'terms' => 'I understand that this action is irreversible.',
    ],

    'reactivation' => [
        'title' => 'Reactivate device',
        'description' => 'To trust this device again, enter a verification code from your authenticator app or one of your recovery codes. Remembering a browser is a temporary convenience, not an independent second authentication factor.',
        'submit' => 'Reactivate',
        'submitting' => 'Reactivating...',
        'why_code_title' => 'Why do we ask for a code?',
        'why_code_before' => 'For security, reactivating',
        'why_code_after' => 'requires you to authenticate again. Enter the 6-digit code from your authenticator app or one of your recovery codes.',
    ],

    'rename' => [
        'title' => 'Rename device',
        'description' => 'Give it a custom name to identify this device easily.',
        'name_label' => 'Device name',
        'name_placeholder' => 'Rename device',
        'name_help' => 'This will be the name you identify this device by.',
        'preview_title' => 'Preview',
        'preview_description' => 'This device will be shown as:',
        'submit' => 'Save changes',
        'submitting' => 'Saving changes...',
    ],

    'renew' => [
        'title' => 'Renew trust',
        'description' => 'Extend the trust period so you are not asked for the verification code on this device. It is a temporary convenience, not an independent second authentication factor. For security, confirm with a code from your authenticator app.',
        'submit' => 'Renew trust',
        'submitting' => 'Renewing...',
        'renewal_period' => '1 month',
        'current_expiration' => 'Current expiration',
        'new_expiration' => 'New expiration',
        'notice' => 'You will not be asked for the verification code on this device until the new expiration date.',
    ],

    'revoke' => [
        'title' => 'Revoke device',
        'description' => 'You will revoke :device. It will no longer be trusted and you will be asked for the verification code again the next time you sign in from it.',
        'submit' => 'Revoke device',
        'submitting' => 'Revoking...',
        'consequences_title' => 'Consequences of revoking',
        'consequences' => [
            'not_trusted' => 'This device will no longer be registered as trusted.',
            'code_again' => 'You will be asked for the verification code the next time you sign in from this device.',
        ],
        'terms' => 'I understand the consequences of revoking this device.',
    ],

    'revoke_all' => [
        'title' => 'Revoke all devices',
        'description' => 'You will revoke every trusted device linked to your account. The next time you sign in from any of them, you will be asked for the verification code again.',
        'single_hint_before' => 'If you only want to remove one, you can revoke it',
        'single_hint_emphasis' => 'individually',
        'single_hint_after' => 'from the device list.',
        'submit' => 'Revoke all',
        'submitting' => 'Revoking...',
        'device_expires' => 'Expires on :date',
        'consequences_title' => 'Consequences of revoking all devices',
        'consequences' => [
            'all_removed' => 'Every trusted device linked to your account will be removed.',
            'code_again' => 'You will be asked for the verification code (2FA) again on those devices.',
            'irreversible' => 'This action cannot be undone.',
        ],
        'devices_count' => '{1} :count device will be revoked|[0,*] :count devices will be revoked',
        'no_devices_title' => 'There are no devices to revoke',
        'includes_all' => 'Includes every trusted device currently registered.',
        'no_devices_description' => 'When you register a new trusted device, it will appear here so you can revoke it along with the others.',
        'empty' => 'You have no registered trusted devices yet.',
        'terms' => 'I understand that this action will remove all my trusted devices.',
    ],

    'revoked' => [
        'title' => 'Device revoked',
        'description' => 'This device was already registered but you revoked it. To trust it again, reactivate it from the list of revoked devices.',
        'go_to_revoked' => 'Go to revoked devices',
        'trust_again_title' => 'Do you want to trust this device again?',
        'trust_again_description' => 'Reactivating it from the revoked list restores the row with a new token for security.',
    ],

    'activity' => [
        'title' => 'Recent device activity',
        'description' => 'History of events related to your trusted devices.',
        'search_label' => 'Search device activity',
        'search_placeholder' => 'Search by device, name or IP...',
        'per_page' => 'Events per page',
        'no_events' => 'No events',
        'showing' => '{1} Showing :from-:to of :total event|[0,*] Showing :from-:to of :total events',
        'empty_filtered_title' => 'No activity for the selected filters.',
        'empty_filtered_description' => 'Try changing the time range, the action type or the search term.',
        'empty_title' => 'No activity recorded yet.',
        'empty_description' => 'The events of your trusted devices will appear here.',
        'unknown_os' => 'Unknown operating system',
        'action_filter' => 'Action',
        'range_filter' => 'Filter by time range',
        'active_filters' => 'Active activity filters',
        'chip_action' => 'Action: :value',
        'chip_range' => 'Range: :value',
        'pagination_label' => 'Device activity pagination',
        'current_page' => 'Current page :page',
        'go_to_page' => 'Go to page :page',
        'actions' => [
            'created' => 'Added',
            'renewed' => 'Renewed',
            'renamed' => 'Renamed',
            'revoked' => 'Revoked',
            'revoked_all' => 'All revoked',
            'reactivated' => 'Reactivated',
        ],
        'since_days' => [
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'last_90_days' => 'Last 90 days',
            'last_180_days' => 'Last 180 days',
            'all' => 'All',
        ],
    ],

    'keyboard_shortcuts' => [
        'description' => 'Global shortcuts start with G. For device actions, select a row by clicking it or focusing its actions button (⋯); then use the shortcuts that start with F.',
        'groups' => [
            'general' => 'GENERAL',
            'navigation' => 'NAVIGATION',
            'actions' => 'ACTIONS',
        ],
        'open_help' => 'Open this help',
        'open_help_keys' => 'Shift and question mark',
        'then' => ', then ',
        'for_selected_row' => ':action for the selected row',
    ],

    'recommendations' => [
        'title' => 'Security recommendations',
        'description' => 'Follow these recommendations to keep your account and trusted devices secure.',
        'footer' => 'Trusted devices speed up your sign-in, but it is worth reviewing them from time to time.',
        'preview_title' => 'How it will look in your list',
        'example' => 'Example',
    ],

    'summary' => [
        'description' => 'Here you can see the breakdown and status of your trusted devices.',
        'percentage_of_total' => ':percentage% of total',
        'desktop' => 'Desktop / Laptop',
        'mobile' => 'Mobile / Tablet',
        'by_type_title' => 'Devices by type',
        'by_type_description' => 'Number of devices per category',
        'no_active' => 'You have no active devices yet.',
        'by_status_title' => 'Distribution by status',
        'by_status_description' => 'Percentage of all devices',
    ],
];

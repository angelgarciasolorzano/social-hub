<?php

declare(strict_types=1);

/**
 * Texts of the trusted devices settings page (`setting/modules/trustedDevice`) outside its dialogs, and
 * the flashes, action names and validation messages of its backend. The dialogs live in
 * `setting/trusted-device-dialogs`; shared buttons such as Cancel live in `setting/common`.
 */
return [
    'page' => [
        'title' => 'Trusted devices',
        'description' => 'These are the devices you have signed in on and marked as trusted.',
        'keyboard_shortcuts' => 'Keyboard shortcuts',
        'manage' => 'Manage',
        'info_banner' => 'Trusted devices let you sign in faster and keep your account protected.',
        'learn_more' => 'Learn more about trusted devices',
        'unrecognized_title' => 'Don\'t recognize a device?',
        'unrecognized_description' => 'Revoke its access from the device menu. If you notice suspicious activity, review your account activity.',
        'review_activity' => 'Review security activity',
        'load_failed' => 'Could not load the information',
        'devices_load_failed' => 'Could not load the trusted devices.',
    ],

    'deferred_errors' => [
        'stats' => 'Could not load your device statistics.',
        'summary' => 'Could not load your device summary.',
        'recent_activity' => 'Could not load the recent activity.',
    ],

    'stats' => [
        'total' => [
            'title' => 'Total devices',
            'description' => 'Registered devices',
        ],
        'active' => [
            'title' => 'Active devices',
            'description' => 'Can currently sign in',
        ],
        'expiring_soon' => [
            'title' => 'Expiring soon',
            'description' => 'In the next 7 days',
        ],
        'recently_added' => [
            'title' => 'Recently added',
            'description' => 'In the last 7 days',
        ],
    ],

    'status' => [
        'active' => 'Active',
        'expired' => 'Expired',
        'inactive' => 'Inactive',
        'revoked' => 'Revoked',
    ],

    'values' => [
        'unknown' => 'Unknown',
        'unknown_device' => 'Unknown device',
        'a_device' => 'A device',
        'not_available' => 'Not available',
        'not_applicable' => 'Not applicable',
    ],

    'columns' => [
        'device' => 'Device',
        'last_access' => 'Last access',
        'browser_os' => 'Browser / OS',
        'expires' => 'Expires',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    'table' => [
        'label' => 'Registered trusted devices',
        'loading' => 'Loading trusted devices',
        'resize_column' => 'Resize column :column',
        'resize_hint' => 'Press and drag to change the width',
        'column_width' => ':size pixels',
        'open_actions' => 'Open actions for :device',
        'empty' => 'You have no registered trusted devices.',
        'empty_filtered_title' => 'No devices match those filters.',
        'empty_filtered_description' => 'Try adjusting or clearing the applied filters.',
    ],

    'toolbar' => [
        'search_label' => 'Search device',
        'search_placeholder' => 'Search device...',
        'clear_search' => 'Clear search',
        'reload' => 'Reload data',
        'columns' => 'Columns',
        'visible_columns' => 'Visible columns',
        'reset_columns' => 'Reset columns',
        'filters' => 'Filters',
        'reset' => 'Reset',
        'clear_filters' => 'Clear filters',
        'no_results' => 'No results',
        'device_type' => 'Device type',
        'sort_by' => 'Sort by',
        'default_sort' => 'Default',
    ],

    'filters' => [
        'browsers' => [
            'other' => 'Other',
        ],
        'device_types' => [
            'desktop' => 'Desktop / Laptop',
            'mobile' => 'Mobile / Tablet',
        ],
        'last_access' => [
            'last_24_hours' => 'Last 24 hours',
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
        ],
    ],

    'chips' => [
        'active_filters' => 'Active filters',
        'active_filters_label' => 'Active filters:',
        'clear_filters' => 'Clear filters',
        'remove_filter' => 'Remove filter: :label',
        'search' => 'Search: :value',
        'status' => 'Status: :value',
        'browser_os' => 'Browser / OS: :value',
        'device_type' => 'Type: :value',
        'last_access' => 'Last access: :value',
    ],

    'pagination' => [
        'label' => 'Trusted devices pagination',
        'page_of' => 'Page :current of :last',
        'rows_per_page' => 'Rows per page',
        'previous' => 'Previous page',
        'next' => 'Next page',
    ],

    'sort' => [
        'most_recent' => [
            'label' => 'Most recent',
            'description' => 'Last access (most recent first)',
        ],
        'oldest' => [
            'label' => 'Oldest',
            'description' => 'Last access (oldest first)',
        ],
        'name_asc' => [
            'label' => 'Name (A - Z)',
            'description' => 'Device name (A to Z)',
        ],
        'name_desc' => [
            'label' => 'Name (Z - A)',
            'description' => 'Device name (Z to A)',
        ],
        'expiring_soon' => [
            'label' => 'Expiring soon',
            'description' => 'Devices that expire first',
        ],
    ],

    'info_card' => [
        'expires_on' => 'Expires on',
        'browser' => 'Browser',
        'system' => 'System',
        'ip_address' => 'IP address',
        'last_access' => 'Last access',
        'identification' => 'How we identify this device',
    ],

    'summary_card' => [
        'os_and_browser' => 'Operating system and browser',
        'last_used' => 'Last used:',
        'expiration_date' => 'Expiration date:',
    ],

    'summary' => [
        'title' => 'Device summary',
        'description' => 'This is how secure your devices are',
        'devices' => 'Devices',
        'active' => 'Active',
        'expiring_soon' => 'Expiring soon',
        'inactive' => 'Inactive',
        'revoked' => 'Revoked',
        'view_details' => 'View details',
    ],

    'recent_activity' => [
        'title' => 'Recent activity',
        'empty_title' => 'No recent activity recorded.',
        'empty_description' => 'The actions you take on your devices will appear here.',
        'view_all' => 'View all activity',
    ],

    'recommendations_panel' => [
        'title' => 'Recommendations',
        'empty_title' => 'No recommendations available.',
        'empty_description' => 'Security recommendations will appear here when they are available.',
        'more' => 'More recommendations',
    ],

    'recommendations' => [
        'review_periodically' => [
            'title' => 'Review your devices regularly',
            'description' => 'Keep only the devices you actually use and recognize. Remove the ones you no longer use.',
        ],
        'avoid_shared' => [
            'title' => 'Don\'t trust shared devices',
            'description' => 'Only mark devices under your control as trusted. Avoid public or shared computers.',
        ],
        'revoke_unknown' => [
            'title' => 'Revoke access you don\'t recognize',
            'description' => 'If you see an unfamiliar device, revoke its access right away to keep unauthorized people out of your account.',
        ],
        'keep_updated' => [
            'title' => 'Keep your browser and system up to date',
            'description' => 'Updates include security patches that reduce the risk of known vulnerabilities.',
        ],
        'preview' => [
            'trusted' => 'Trusted',
            'revoke' => 'Revoke',
            'desktop' => 'Desktop',
            'mobile' => 'Mobile',
            'footer_title' => 'Small actions, big difference',
            'footer_description' => 'Every device you review strengthens your account security.',
        ],
    ],

    'shortcuts' => [
        'add_device' => 'Add a trusted device',
        'activity' => 'Open all activity',
        'recommendations' => 'Open recommendations',
        'summary' => 'Open the device summary',
        'revoke_all' => 'Revoke all devices',
    ],

    'row_actions' => [
        'menu' => 'Device actions',
        'view' => 'View device',
        'rename' => 'Rename device',
        'renew' => 'Renew trust',
        'reactivate' => 'Reactivate',
        'revoke' => 'Revoke device',
        'force_destroy' => 'Delete permanently',
    ],

    'header_actions' => [
        'add' => 'Add device',
        'revoke_all' => 'Revoke all',
    ],

    /*
     * Flash messages of `TrustedDeviceOperationResult::message()`, one per case.
     */
    'results' => [
        'created' => 'Device added successfully.',
        'already_active' => 'This device is already registered as trusted.',
        'already_revoked' => 'This device is already registered but was revoked. Reactivate it from the list of revoked devices instead of adding it again.',
        'renamed' => 'Device renamed successfully.',
        'renewed' => 'Trust renewed successfully.',
        'revoked' => 'Trusted device revoked successfully.',
        'revoked_all' => 'All trusted devices were revoked.',
        'reactivated' => 'Device reactivated successfully. The trust token was regenerated for security.',
        'force_deleted' => 'Device permanently deleted.',
        'two_factor_required' => 'You must first enable and confirm two-factor authentication to manage trusted devices.',
    ],

    /*
     * Names of the `TrustedDeviceAction` cases, keyed by their stored value.
     */
    'actions' => [
        'created' => 'Device added',
        'renewed' => 'Trust renewed',
        'renamed' => 'Device renamed',
        'revoked' => 'Device revoked',
        'revoked_all' => 'All devices revoked',
        'reactivated' => 'Device reactivated',
    ],

    'validation' => [
        'code_required' => 'You must enter the verification code.',
        'code_length' => 'The code must have 6 digits.',
        'code_digits' => 'The code can only contain numbers.',
        'code_incorrect' => 'The verification code is incorrect.',
        'code_too_long' => 'The code is too long.',
        'code_format' => 'Enter a 6-digit code or a valid recovery code.',
        'name_required' => 'The name is required.',
        'name_max' => 'The name may not be longer than :max characters.',
        'password_required' => 'You must confirm your password.',
        'password_incorrect' => 'The password is incorrect.',
        'terms_required' => 'You must confirm that you understand the consequences.',
        'terms_accepted' => 'You must accept the terms to continue.',
    ],
];

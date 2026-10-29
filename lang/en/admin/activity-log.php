<?php

declare(strict_types=1);

/**
 * Texts of the activity log resource, its table and detail, its summary widgets, and the readable
 * names of every cataloged event (keyed by its stored value, `log.action`) and log.
 */
return [
    'resource' => [
        'label' => 'activity',
        'plural_label' => 'activity log',
        'navigation_label' => 'Activity log',
    ],

    'infolist' => [
        'activity' => [
            'heading' => 'Activity',
            'description' => 'What happened, when, who did it, and to what.',
        ],
        'details' => [
            'heading' => 'Details',
            'description' => 'The references stored with this activity. Content is never recorded.',
            'empty' => 'No details recorded',
        ],
        'request' => [
            'heading' => 'Request',
            'description' => 'Where the action came from, when it was made through the web.',
            'ip_address' => 'IP address',
            'user_agent' => 'User agent',
            'not_recorded' => 'Not recorded',
        ],
        'values' => [
            'yes' => 'Yes',
            'no' => 'No',
        ],
    ],

    'overview' => [
        'heading' => 'Security and administration (last 7 days)',
        'failed_sign_ins' => 'Failed sign-ins',
        'failed_sign_ins_description' => 'Authentication attempts that were rejected',
        'trusted_device_changes' => 'Trusted device changes',
        'trusted_device_changes_description' => 'Devices added, renamed, renewed, revoked or reactivated',
        'admin_actions' => 'Admin actions',
        'admin_actions_description' => 'Roles, permissions, invitations, accounts and backups',
    ],

    'summary' => [
        'heading' => 'Activity events (last 7 days)',
        'empty_state_heading' => 'No activity in the last 7 days',
        'empty_state_description' => 'There are no activity events to summarize for this period.',
        'description' => '{0} :count events in the last 7 days.|{1} :count event in the last 7 days.|[2,*] :count events in the last 7 days.',
        'dataset_events' => 'Events',
        'unknown_event' => 'Unspecified event',
        'assistive_empty' => 'There are no activity events to summarize for the last 7 days.',
        'assistive_distribution' => 'Event distribution: :distribution.',
    ],

    'table' => [
        'log' => 'Log',
        'event' => 'Event',
        'performed_by' => 'Performed by',
        'subject' => 'Subject',
    ],

    'parties' => [
        'deleted_causer' => 'Deleted :type #:id',
        'subject' => ':type #:id',
        'deleted_subject' => ':type #:id (deleted)',
        'types' => [
            'user' => 'User',
            'post' => 'Post',
            'like' => 'Like',
            'comment' => 'Comment',
            'admin_invitation' => 'Admin Invitation',
            'trusted_device_event' => 'Trusted Device Event',
            'account_status_change' => 'Account Status Change',
            'announcement' => 'Announcement',
            'role' => 'Role',
        ],
    ],

    'logs' => [
        'auth' => 'Auth',
        'content' => 'Content',
        'likes' => 'Likes',
        'trusted_devices' => 'Trusted Devices',
        'account' => 'Account',
        'admin' => 'Admin',
    ],

    'events' => [
        'auth' => [
            'login' => 'User Logged In',
            'logout' => 'User Logged Out',
            'login_failed' => 'Login Failed',
            'password_updated' => 'Password Updated',
            'password_reset' => 'Password Reset',
            'two_factor_confirmed' => 'Two Factor Confirmed',
            'two_factor_disabled' => 'Two Factor Disabled',
            'recovery_codes_regenerated' => 'Recovery Codes Regenerated',
        ],
        'content' => [
            'post_created' => 'Post Created',
            'comment_created' => 'Comment Created',
            'comment_replied' => 'Comment Replied',
            'comment_updated' => 'Comment Updated',
            'comment_deleted' => 'Comment Deleted',
        ],
        'likes' => [
            'added' => 'Like Added',
            'removed' => 'Like Removed',
        ],
        'trusted_devices' => [
            'created' => 'Trusted Device Added',
            'renewed' => 'Trusted Device Renewed',
            'renamed' => 'Trusted Device Renamed',
            'revoked' => 'Trusted Device Revoked',
            'revoked_all' => 'All Trusted Devices Revoked',
            'reactivated' => 'Trusted Device Reactivated',
        ],
        'account' => [
            'updated' => 'Updated',
            'image_updated' => 'Image Updated',
            'deleted' => 'Deleted',
        ],
        'admin' => [
            'role_granted' => 'Role Granted',
            'role_revoked' => 'Role Revoked',
            'role_permission_granted' => 'Role Permission Granted',
            'role_permission_revoked' => 'Role Permission Revoked',
            'bootstrapped' => 'Admin Bootstrapped',
            'invitation_issued' => 'Invitation Issued',
            'invitation_accepted' => 'Invitation Accepted',
            'backup_created' => 'Backup Created',
            'backup_downloaded' => 'Backup Downloaded',
            'account_suspended' => 'Account Suspended',
            'account_reactivated' => 'Account Reactivated',
            'announcement_created' => 'Announcement Created',
            'announcement_updated' => 'Announcement Updated',
            'announcement_unpublished' => 'Announcement Unpublished',
            'announcement_duplicated' => 'Announcement Duplicated',
            'announcement_deleted' => 'Announcement Deleted',
        ],
    ],
];

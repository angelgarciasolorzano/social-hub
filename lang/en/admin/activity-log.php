<?php

declare(strict_types=1);

/**
 * Texts of the activity log table, how its parties are described, and the readable names of every
 * cataloged event (keyed by its stored value, `log.action`) and log.
 */
return [
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

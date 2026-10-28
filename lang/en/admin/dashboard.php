<?php

declare(strict_types=1);

/**
 * Texts of the admin dashboard widgets.
 */
return [
    'quick_links' => [
        'heading' => 'Quick access',
        'description' => 'Open an area you have permission to access.',
        'aria_label' => 'Dashboard quick links',
        'links' => [
            'users' => [
                'label' => 'Users',
                'description' => 'Review accounts and account status',
            ],
            'posts' => [
                'label' => 'Posts',
                'description' => 'Review posts and comments',
            ],
            'activity_log' => [
                'label' => 'Activity log',
                'description' => 'Review security and admin events',
            ],
            'backups' => [
                'label' => 'Backups',
                'description' => 'Check backup availability',
            ],
            'announcements' => [
                'label' => 'Announcements',
                'description' => 'Publish notices for application users',
            ],
        ],
    ],

    'operational_summary' => [
        'heading' => 'Operational summary',
        'account_status' => [
            'label' => 'Account status',
            'active' => '{1} :count active|[0,*] :count active',
            'suspended' => '{1} :count suspended|[0,*] :count suspended',
            'recent_changes' => '{1} :count status change in the last 7 days|[0,*] :count status changes in the last 7 days',
            'unavailable' => 'Account metrics could not be loaded',
        ],
        'activity' => [
            'label' => 'Activity (7 days)',
            'description' => 'Recorded events in the activity log',
            'unavailable' => 'Activity metrics could not be loaded',
        ],
        'backups' => [
            'label' => 'Latest backup',
            'none_found' => 'No backups found · 0/0 available',
            'available' => '{1} :available/:total available|[0,*] :available/:total available',
            'destination_healthy' => 'Destination healthy',
            'destination_needs_attention' => 'Destination needs attention',
            'unavailable' => 'Backup summary could not be loaded',
        ],
    ],

    'registration_summary' => [
        'heading' => 'Account overview',
        'total' => [
            'label' => 'Total accounts',
            'description' => 'All registered user accounts',
        ],
        'verified' => [
            'label' => 'Verified accounts',
            'description' => 'Accounts with a verified email address',
        ],
        'pending' => [
            'label' => 'Pending verification',
            'description' => 'Accounts waiting to verify their email address',
        ],
    ],

    'registrations_chart' => [
        'heading' => 'New user registrations',
        'description' => 'Track account registrations over time.',
        'empty_heading' => 'No registrations yet',
        'empty_description' => 'There are no account registrations in this period.',
        'dataset' => 'Registrations',
        'filters' => [
            '7_days' => 'Last 7 days',
            '30_days' => 'Last 30 days',
            '90_days' => 'Last 90 days',
            '12_months' => 'Last 12 months',
        ],
        'date_formats' => [
            'day' => 'M j',
            'month' => 'M Y',
        ],
    ],
];

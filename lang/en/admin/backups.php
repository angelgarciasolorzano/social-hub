<?php

declare(strict_types=1);

/**
 * Texts of the Backups page of the admin panel: the page, its actions, table, destination and
 * retention details, summary widget and download errors.
 */
return [
    'page' => [
        'title' => 'Backup management',
        'navigation_group' => 'Administration',
        'navigation_label' => 'Backups',
    ],

    'date_format' => 'M j, Y H:i',

    'disabled' => 'Disabled',

    'actions' => [
        'create' => [
            'label' => 'Create backup',
            'modal_heading' => 'Create a database backup?',
            'modal_description' => 'The backup will be queued and appear in this list after the queue finishes processing it.',
            'submit' => 'Queue backup',
            'success' => 'Database backup queued',
        ],
        'download' => [
            'label' => 'Download',
            'current_password' => 'Current password',
            'modal_heading' => 'Confirm your password to download this backup',
            'submit' => 'Download backup',
        ],
    ],

    'errors' => [
        'backup_unavailable' => 'The selected backup is no longer available.',
        'password_required' => 'The current password is required.',
        'password_incorrect' => 'The password is incorrect.',
        'too_many_attempts' => 'Too many password attempts. Please try again in :seconds seconds.',
    ],

    'table' => [
        'heading' => 'Backups',
        'archive' => 'Archive',
        'created' => 'Created',
        'size' => 'Size',
        'availability' => 'Availability',
        'available' => 'Available',
        'missing' => 'Missing',
        'empty_heading' => 'No backups yet',
        'empty_description' => 'A database backup will appear here after the first run.',
    ],

    'destination' => [
        'heading' => 'Backup destination',
        'description' => 'Storage target, availability, and protection for generated backup archives.',
        'health' => 'Health',
        'health_details' => 'Health details',
        'failed_checks' => 'Failed checks',
        'provider' => 'Storage provider',
        'bucket' => 'Bucket',
        'prefix' => 'Backup prefix',
        'region' => 'Region',
        'access' => 'Access',
        'archive_encryption' => 'Archive encryption',
        'healthy' => 'Healthy',
        'unhealthy' => 'Unhealthy',
        'all_checks_passed' => 'All configured backup health checks passed.',
        'checks_failed' => 'One or more configured backup health checks failed.',
        'not_configured' => 'Not configured',
        'providers' => [
            's3_compatible' => 'S3-compatible storage',
        ],
        'visibilities' => [
            'private' => 'Private',
            'public' => 'Public',
        ],
    ],

    'retention' => [
        'heading' => 'Retention policy',
        'description' => 'Automated cleanup schedule for backup archives.',
        'all_backups' => 'All backups',
        'daily_snapshots' => 'Daily snapshots',
        'weekly_snapshots' => 'Weekly snapshots',
        'monthly_snapshots' => 'Monthly snapshots',
        'yearly_snapshots' => 'Yearly snapshots',
        'days' => '{1} :count day|[0,*] :count days',
        'weeks' => '{1} :count week|[0,*] :count weeks',
        'months' => '{1} :count month|[0,*] :count months',
        'years' => '{1} :count year|[0,*] :count years',
    ],

    'widget' => [
        'heading' => 'Backup summary',
        'listing_unavailable' => 'Backup listing is unavailable.',
        'destination_healthy' => 'Destination healthy.',
        'destination_needs_attention' => 'Destination needs attention.',
        'no_backups_found' => 'No backups found.',
        'available' => [
            'label' => 'Available backups',
            'description' => 'Archives present in storage',
        ],
        'missing' => [
            'label' => 'Missing backups',
            'description' => 'Archives not found in storage',
            'none' => 'No missing archives',
        ],
        'latest' => [
            'label' => 'Latest available backup',
            'none' => 'No available backups',
        ],
        'total_size' => [
            'label' => 'Total backup size',
            'description' => 'Combined size of listed archives',
            'sizes_unavailable' => 'One or more archive sizes are unavailable',
            'none' => 'No backups stored',
        ],
    ],
];

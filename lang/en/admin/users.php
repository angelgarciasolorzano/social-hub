<?php

declare(strict_types=1);

/**
 * Texts of the Users section of the admin panel: the resource, its table, detail, actions,
 * relation managers and widgets.
 */
return [
    'resource' => [
        'model_label' => 'user',
        'plural_model_label' => 'users',
        'navigation_label' => 'Users',
    ],

    'account_status' => [
        'label' => 'Account status',
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],

    'fields' => [
        'profile_picture' => 'Profile picture',
        'cover_image' => 'Cover image',
        'email_address' => 'Email address',
        'email_verification' => 'Email verification',
    ],

    'table' => [
        'profile_picture_alt' => 'Profile picture of :name',
        'name' => 'Name',
        'email' => 'Email',
        'email_verified_at' => 'Email verified at',
        'created_at' => 'Created at',
        'last_login_at' => 'Last login at',
        'filters' => [
            'registration_date' => 'Registration date',
            'post_presence' => 'Post presence',
            'all_users' => 'All users',
            'has_posts' => 'Has posts',
            'no_posts' => 'No posts',
        ],
    ],

    'invite' => [
        'label' => 'Invite administrator',
        'modal_heading' => 'Invite administrator',
        'submit' => 'Send invitation',
        'success' => 'Invitation email queued',
        'email_helper' => 'Search by name or email. To invite someone new, enter their full email and select the invite option.',
        'new_email_option' => 'Invite new email address: :email',
    ],

    'infolist' => [
        'profile' => [
            'heading' => 'Profile',
            'description' => 'Identity and profile images for this user.',
        ],
        'full_name' => 'Full name',
        'cover_image_uploaded' => 'Uploaded',
        'cover_image_not_added' => 'Not added',
        'account_activity' => [
            'heading' => 'Account activity',
            'description' => 'Email verification and sign-in history for this user.',
        ],
        'verified' => 'Verified',
        'not_verified' => 'Not verified',
        'verified_at' => 'Verified at',
        'created_at' => 'Account created',
        'last_sign_in' => 'Last sign-in',
        'never_signed_in' => 'Never signed in',
    ],

    'actions' => [
        'suspend' => [
            'label' => 'Suspend account',
            'modal_heading' => 'Suspend account',
            'modal_description' => 'The account is signed out everywhere, its trusted devices are revoked and it cannot sign in until it is reactivated. Its data is kept.',
            'submit' => 'Suspend',
            'success' => 'Account suspended',
            'failure' => 'The account was already suspended; reload the page to see its current status',
        ],
        'reactivate' => [
            'label' => 'Reactivate account',
            'modal_heading' => 'Reactivate account',
            'modal_description' => 'The account can sign in again. Trusted devices revoked by the suspension stay revoked.',
            'submit' => 'Reactivate',
            'success' => 'Account reactivated',
            'failure' => 'The account was already active; reload the page to see its current status',
        ],
    ],

    'relations' => [
        'status_history' => [
            'title' => 'Status history',
            'changed_by' => 'Changed by',
        ],
        'activity' => [
            'title' => 'Activity',
        ],
        'posts' => [
            'title' => 'Posts',
            'image' => 'Image',
            'image_alt' => 'Post image',
            'post' => 'Post',
            'created_at' => 'Created at',
            'filters' => [
                'comment_presence' => 'Comment presence',
                'all_posts' => 'All posts',
                'has_comments' => 'Has comments',
                'no_comments' => 'No comments',
                'creation_date' => 'Creation date',
            ],
        ],
        'trusted_devices' => [
            'title' => 'Trusted devices',
            'device' => 'Device',
            'unknown_device' => 'Unknown device',
            'browser_os' => 'Browser / OS',
            'unknown' => 'Unknown',
            'last_used' => 'Last used',
            'expires' => 'Expires',
            'statuses' => [
                'active' => 'Active',
                'expired' => 'Expired',
                'revoked' => 'Revoked',
            ],
        ],
    ],

    'widgets' => [
        'activity_overview' => [
            'posts' => [
                'label' => 'Posts',
                'description' => 'New posts per day over the last 7 days',
            ],
            'comments' => [
                'label' => 'Comments',
                'description' => 'New comments per day over the last 7 days',
            ],
            'active_trusted_devices' => [
                'label' => 'Active trusted devices',
                'description' => 'Not revoked and not expired',
            ],
            'status_changes' => [
                'label' => 'Status changes (30 days)',
                'description' => 'Account suspensions and reactivations',
            ],
        ],
    ],
];

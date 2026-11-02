<?php

declare(strict_types=1);

/**
 * Texts of the Profile settings page (`setting/modules/accountSettings`) and of the flashes its
 * actions answer with. Shared buttons such as Cancel live in `setting/common`.
 */
return [
    'page_title' => 'Account settings',

    'overview' => [
        'title' => 'Your profile information',
        'description' => 'Manage the personal information associated with your account.',
        'hint' => 'Keep your details up to date for a better experience.',
        'manage_title' => 'Manage your data',
        'manage_description' => 'Update the available personal information and see how your public profile is shown.',
    ],

    'privacy_principles' => [
        'account_data' => [
            'title' => 'Your account data',
            'description' => 'View and update the personal information associated with your account.',
        ],
        'public_profile' => [
            'title' => 'Public profile',
            'description' => 'See how the information of your public profile is shown.',
        ],
        'your_choice' => [
            'title' => 'You decide',
            'description' => 'You can update your data or delete your account from the available options.',
        ],
        'account_options' => [
            'title' => 'Account options',
            'description' => 'Review the actions available to manage your account.',
        ],
    ],

    'communication' => [
        'title' => 'Communication preferences',
        'description' => 'A visual example of the types of communication available on the platform.',
        'channels' => [
            'email' => [
                'title' => 'Emails',
                'description' => 'Receive important updates and news.',
            ],
            'system' => [
                'title' => 'System notifications',
                'description' => 'Alerts and messages within the platform.',
            ],
            'news' => [
                'title' => 'News and tips',
                'description' => 'Receive tips, improvements and product news.',
            ],
        ],
    ],

    'profile_form' => [
        'title' => 'Personal information',
        'description' => 'Update the personal details that are part of your profile.',
        'name' => 'Name (required)',
        'phone' => 'Phone (optional)',
        'phone_placeholder' => 'Enter your phone number here, e.g. +56 987654321',
        'email' => 'Email',
        'email_help' => 'The email is shown for reference and cannot be edited here.',
        'locale' => 'Preferred language (required)',
        'locale_help' => 'Your preferred language is saved to your account.',
        'biography' => 'Biography (optional)',
        'biography_help' => 'Maximum :max characters.',
        'biography_count' => ':count / :max characters',
        'save' => 'Save changes',
        'saving' => 'Saving changes…',
        'saved' => 'Changes saved successfully.',
    ],

    /*
     * Each language is named in its own language, so both catalogs share these texts.
     */
    'locales' => [
        'en' => 'English',
        'es' => 'Español',
    ],

    'summary' => [
        'title' => 'Account summary',
        'description' => 'Your profile information.',
        'view_activity' => 'View activity',
        'user' => 'User',
        'email' => 'Email',
        'status' => 'Account status',
        'member_since' => 'Member since',
        'last_login' => 'Last sign-in',
    ],

    'status' => [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],

    'actions' => [
        'public_profile' => [
            'title' => 'Public profile',
            'description' => 'See how your public profile is shown.',
            'link' => 'Go to my public profile',
        ],
        'password' => [
            'title' => 'Change password',
            'description' => 'Choose a strong password that you do not use on other sites.',
        ],
        'delete' => [
            'title' => 'Delete account',
            'description' => 'This action is permanent and you will lose access to your account.',
        ],
    ],

    'password_dialog' => [
        'trigger' => 'Change password',
        'title' => 'Change password',
        'description' => 'Update your password to keep your account secure.',
        'revocation_title' => 'Your devices will stop being trusted',
        'revocation_description' => 'When you change your password, every device associated with your account will stop being trusted. If you have two-factor authentication enabled, you will have to verify yourself again the next time you sign in from those devices. Sessions that are already open will stay active.',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'password_confirmation' => 'Confirm password',
        'submit' => 'Save password',
        'submitting' => 'Updating...',
    ],

    'delete_dialog' => [
        'trigger' => 'Delete my account',
        'title' => 'Delete my account',
        'description' => 'To continue, confirm the deletion with your current password.',
        'warning_title' => 'This action is permanent',
        'warning_description' => 'Your account and the profile information saved in it will be permanently deleted. You will be signed out and will no longer be able to sign in with these credentials; to use the platform again you will have to register again. This action cannot be undone.',
        'current_password' => 'Current password',
        'submit' => 'Delete account',
        'submitting' => 'Deleting...',
    ],

    'messages' => [
        'profile_updated' => 'Details updated successfully.',
        'password_updated' => 'Password updated successfully',
    ],
];

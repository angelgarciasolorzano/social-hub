<?php

declare(strict_types=1);

/**
 * Texts of the Announcements section of the admin panel: the resource, form, table, detail and preview.
 */
return [
    'resource' => [
        'model_label' => 'announcement',
        'plural_model_label' => 'announcements',
        'navigation_label' => 'Announcements',
    ],

    'sections' => [
        'content' => 'Content',
        'display' => 'Display',
        'button' => 'Button',
        'history' => 'History',
    ],

    'fields' => [
        'title' => 'Title',
        'message' => 'Message',
        'image' => 'Image',
        'type' => 'Type',
        'audience' => 'Audience',
        'display' => 'Shown as',
        'is_dismissible' => 'Users can dismiss it',
        'starts_at' => 'Starts at',
        'ends_at' => 'Ends at',
        'button_text' => 'Button text',
        'button_link' => 'Button link',
        'created_by' => 'Created by',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'form' => [
        'has_button' => 'Has button',
        'ends_at_helper' => 'Leave empty to keep it until it is unpublished.',
        'button_link_helper' => 'An https:// address or a path of this site, such as /setting/two-factor-authentication.',
        'button_link_invalid' => 'The link must start with https:// or be a path of this site starting with /.',
        'overlapping_dialogs' => '{1} :count other dialog is shown in the same period.|[2,*] :count other dialogs are shown in the same period.',
    ],

    'options' => [
        'type' => [
            'info' => 'Information',
            'warning' => 'Warning',
            'critical' => 'Critical',
        ],
        'audience' => [
            'all' => 'Everyone',
            'authenticated' => 'Signed-in users',
            'without_two_factor' => 'Users without two-factor authentication',
        ],
        'display' => [
            'toast' => 'Toast',
            'dialog' => 'Dialog',
            'toast_description' => 'A floating notice that does not interrupt. Best for news and routine notices.',
            'dialog_description' => 'A window the user must close before going on. Keep it for urgent notices.',
        ],
    ],

    'table' => [
        'starts' => 'Starts',
        'ends' => 'Ends',
        'no_end_date' => 'No end date',
        'deleted_user' => 'Deleted user',
    ],

    'filters' => [
        'type' => 'Type',
        'audience' => 'Audience',
        'display' => 'Shown as',
    ],

    'tabs' => [
        'scheduled' => 'Scheduled',
        'active' => 'Active',
        'expired' => 'Expired',
        'active_dialogs' => 'Active dialogs',
        'all' => 'All',
        'active_dialogs_tooltip' => 'Dialogs interrupt users until they close them.',
    ],

    'actions' => [
        'preview' => 'Preview',
        'preview_heading' => 'Banner preview',
        'close' => 'Close',
        'duplicate' => 'Duplicate',
        'duplicate_description' => 'The copy starts tomorrow and has no end date, so it is not shown before you review it.',
        'duplicated' => 'Announcement duplicated',
        'unpublish' => 'Unpublish now',
        'unpublish_description' => 'Users stop seeing this announcement right away.',
        'unpublished' => 'Announcement unpublished',
        'no_longer_running' => 'The announcement is no longer running.',
        'administrator_only' => 'Only a signed-in administrator can change announcements.',
    ],

    'placeholders' => [
        'no_image' => 'No image',
        'no_end_date' => 'No end date',
        'no_button' => 'No button',
        'deleted_user' => 'Deleted user',
    ],

    'preview' => [
        'acknowledge' => 'Understood',
        'close_disabled' => 'Close (disabled in preview)',
        'shown_as' => 'Shown as :display to :audience',
        'dismissible' => 'remains hidden once closed',
        'not_dismissible' => 'comes back in the next session after being closed',
        'final_look_note' => 'The final appearance in the application may differ slightly.',
    ],
];

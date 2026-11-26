<?php

declare(strict_types=1);

/**
 * Texts of the two sidebars of `HomeLayout` and of its mobile menu: the app navigation on the
 * left, the reserved panel on the right and the menu sheet of small screens. Shared on every
 * page that uses the layout.
 */
return [
    'navigation' => [
        'label' => 'Main navigation',
        'section' => 'Menu',
        'home' => 'Home',
        'search' => 'Find people',
        'friends' => 'Friends',
        'notifications' => 'Notifications',
        'profile' => 'My profile',
        'pending_requests_count' => '{0} No pending friend requests|{1} :count pending friend request|[2,*] :count pending friend requests',
        'unread_notifications_count' => '{0} No unread notifications|{1} :count unread notification|[2,*] :count unread notifications',
        'loading_count' => 'Loading the counter',
    ],

    'reserved' => [
        'title' => 'Coming soon',
        'description' => 'This space is reserved for upcoming features.',
    ],

    'mobile' => [
        'open' => 'Open the menu',
        'close' => 'Close the menu',
        'title' => 'Menu',
    ],
];

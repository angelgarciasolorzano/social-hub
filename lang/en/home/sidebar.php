<?php

declare(strict_types=1);

/**
 * Texts of the two sidebars of `HomeLayout` and of its mobile menu: the app navigation on the
 * left, the reserved panel on the right and the menu sheet of small screens. Shared on every
 * page, because the layout appears on every page that uses it. The `friend_requests` keys go
 * away with the old requests panel.
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

    'friend_requests' => [
        'title' => 'Friend requests',
        'empty_title' => 'No pending requests',
        'empty_description' => 'When someone sends you a friend request, it will appear here.',
        'view_all' => 'View all requests',
        'loading' => 'Loading the friend requests',
        'profile_picture_alt' => 'Profile picture of :name',
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

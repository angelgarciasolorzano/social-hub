<?php

declare(strict_types=1);

/**
 * Texts of the two sidebars of `HomeLayout`: the app navigation on the left and the received
 * friend requests panel on the right. Shared on every page, because both sidebars appear on
 * every page that uses the layout.
 */
return [
    'navigation' => [
        'label' => 'Main navigation',
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
];

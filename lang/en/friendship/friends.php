<?php

declare(strict_types=1);

/**
 * Texts of the friends page (`modules/friendship/Friends`): its tabs, the name filter, the
 * sort, the friend requests block, the cards, the empty and loading states, the keyboard
 * shortcuts and the validation messages of its request.
 */
return [
    'page' => [
        'title' => 'Friends',
        'description' => 'Your friends and the friend requests you sent or received.',
    ],

    'tabs' => [
        'label' => 'Friend lists',
        'friends' => 'Friends',
        'received' => 'Received',
        'sent' => 'Sent',
        'friends_count' => '{0} No friends|{1} :count friend|[2,*] :count friends',
        'received_count' => '{0} No received requests|{1} :count received request|[2,*] :count received requests',
        'sent_count' => '{0} No sent requests|{1} :count sent request|[2,*] :count sent requests',
    ],

    'filter' => [
        'label' => 'Filter by name',
        'placeholder' => 'Filter by name',
        'clear' => 'Clear the filter',
    ],

    'toolbar' => [
        'reload' => 'Reload the list',
    ],

    'sort' => [
        'label' => 'Sort the list',
        'recent' => 'Most recent',
        'name' => 'Name (A-Z)',
        'title' => 'Sort by',
        'recent_description' => 'Newest friendship or request first',
        'name_description' => 'Name of the person, A to Z',
        'default' => 'Default',
        'reset' => 'Reset',
    ],

    'requests' => [
        'title' => 'Friend requests',
        'view_all' => '{1} View the request|[2,*] View all :count requests',
        'label' => 'Latest friend requests you received',
    ],

    'states' => [
        'friends_empty_title' => 'You have no friends yet',
        'friends_empty_description' => 'Find people and send them a friend request.',
        'received_empty_title' => 'No pending requests',
        'received_empty_description' => 'When someone sends you a friend request, it will appear here.',
        'sent_empty_title' => 'No sent requests',
        'sent_empty_description' => 'The friend requests you send and that are still waiting for an answer will appear here.',
        'filtered_empty_title' => 'Nobody matches ":term"',
        'filtered_empty_description' => 'Check the spelling or try with the first letters of another name.',
        'find_people' => 'Find people',
        'loading' => 'Loading the list',
        'loading_more' => 'Loading more people',
        'end_of_list' => 'You reached the end of the list',
    ],

    'row' => [
        'profile_picture_alt' => 'Profile picture of :name',
        'friends_since' => 'Friends since :date',
        'request_sent_at' => 'Sent :date',
    ],

    'card' => [
        'profile_picture_alt' => 'Profile picture of :name',
        'mutual_friends' => '{0} No friends in common|{1} :count friend in common|[2,*] :count friends in common',
        'view_profile' => 'View profile',
        'more_options' => 'More options for :name',
    ],

    'shortcuts' => [
        'title' => 'Keyboard shortcuts',
        'description' => 'Move around the friends page without the mouse. Shortcuts do not work while you type in a field.',
        'open' => 'Show the keyboard shortcuts',
        'tabs' => 'Switch tabs',
        'tabs_description' => 'Friends, Received and Sent',
        'focus_filter' => 'Focus the name filter',
        'toggle_sort' => 'Switch between most recent and name',
        'show_help' => 'Show this help',
        'leave_filter' => 'Leave the name filter so the shortcuts work',
        'got_it' => 'Got it',
    ],

    'validation' => [
        'tab_invalid' => 'The selected list does not exist.',
        'sort_invalid' => 'The selected order does not exist.',
        'q_max' => 'The filter cannot be longer than :max characters.',
    ],
];

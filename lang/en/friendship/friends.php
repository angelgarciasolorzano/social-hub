<?php

declare(strict_types=1);

/**
 * Texts of the friends page (`modules/friendship/Friends`): its tabs, the name filter, the
 * empty and loading states, the list rows and the validation messages of its request.
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
        'placeholder' => 'Type a name',
        'clear' => 'Clear the filter',
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
    ],

    'row' => [
        'profile_picture_alt' => 'Profile picture of :name',
        'friends_since' => 'Friends since :date',
        'request_sent_at' => 'Sent :date',
    ],

    'validation' => [
        'tab_invalid' => 'The selected list does not exist.',
        'q_max' => 'The filter cannot be longer than :max characters.',
    ],
];

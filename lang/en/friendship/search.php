<?php

declare(strict_types=1);

/**
 * Texts of the people search page (`modules/friendship/Search`) and of its result rows.
 */
return [
    'page' => [
        'title' => 'Find people',
        'description' => 'Search people by name and send them a friend request.',
    ],

    'form' => [
        'label' => 'Search people by name',
        'placeholder' => 'Type a name',
        'clear' => 'Clear the search',
    ],

    'states' => [
        'prompt_title' => 'Find your friends',
        'prompt_description' => 'Type at least :min characters of a name to start searching.',
        'empty_title' => 'Nobody matches ":term"',
        'empty_description' => 'Check the spelling or try with the first letters of another name.',
        'loading' => 'Searching people',
        'loading_more' => 'Loading more people',
    ],

    'result' => [
        'profile_picture_alt' => 'Profile picture of :name',
        'mutual_friends' => '{1} :count friend in common|[2,*] :count friends in common',
        'view_profile' => 'View profile',
    ],
];

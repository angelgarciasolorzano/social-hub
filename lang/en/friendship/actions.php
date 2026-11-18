<?php

declare(strict_types=1);

/**
 * Messages of the friendship actions and of the people search, sent by the backend to the
 * React application. The results are keyed by the values of `FriendshipOperationResult`.
 */
return [
    'results' => [
        'sent' => 'Friend request sent.',
        'accepted' => 'Friend request accepted. You are now friends.',
        'declined' => 'Friend request declined.',
        'canceled' => 'Friend request canceled.',
        'removed' => 'Friend removed.',
        'already_pending' => 'You already sent a friend request to this person.',
        'already_friends' => 'You are already friends with this person.',
        'incoming_request_exists' => 'This person already sent you a friend request. Accept it from your received requests.',
        'cooldown_active' => 'You cannot send a friend request to this person right now. Try again later.',
        'pending_limit_reached' => 'You have too many friend requests waiting for an answer. Cancel some before sending new ones.',
        'receiver_unavailable' => 'You cannot send a friend request to this person.',
        'no_longer_pending' => 'This request or friendship has already changed. Reload the page to see its current state.',
    ],

    'validation' => [
        'search_required' => 'Type a name to search.',
        'search_min' => 'Type at least :min characters to search.',
        'search_max' => 'The search cannot be longer than :max characters.',
    ],
];

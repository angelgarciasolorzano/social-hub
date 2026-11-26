<?php

declare(strict_types=1);

/**
 * Texts of the reusable friendship button (`modules/friendship/components/FriendshipButton`)
 * and of its remove-friend dialog. Shared on every page, because the button appears on the
 * search page, the friends page, the profile and the sidebar.
 */
return [
    'states' => [
        'add' => 'Add friend',
        'request_sent' => 'Request sent',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'friends' => 'Friends',
    ],

    'menu' => [
        'label' => 'Friendship options with :name',
        'cancel_request' => 'Cancel request',
        'remove_friend' => 'Remove friend',
    ],

    'remove_dialog' => [
        'title' => 'Remove :name from your friends?',
        'description' => 'You will remove :name from your friends list.',
        'friends_since' => 'Friends since :date',
        'mutual_friends' => '{1} :count mutual friend|[2,*] :count mutual friends',
        'consequences' => [
            'feed_title' => 'Their posts leave your home feed',
            'feed' => 'And yours stop showing up in :name\'s home feed.',
            'no_notification_title' => 'They will not be told',
            'no_notification' => ':name will not get any notification.',
            'request_again_title' => 'You can be friends again',
            'request_again' => 'Either of you can send a request at any time.',
        ],
        'confirm' => 'Remove friend',
        'removing' => 'Removing…',
        'cancel' => 'Keep as friend',
    ],

    'processing' => [
        'sending' => 'Sending the friend request to :name',
        'accepting' => 'Accepting the friend request from :name',
        'declining' => 'Declining the friend request from :name',
        'canceling' => 'Canceling the friend request to :name',
        'removing' => 'Removing :name from your friends',
    ],

    'profile' => [
        'friends_count' => '{0} No friends|{1} :count friend|[2,*] :count friends',
        'mutual_friends' => '{1} :count friend in common|[2,*] :count friends in common',
    ],

    'error' => 'The action could not be completed. Try again.',
];

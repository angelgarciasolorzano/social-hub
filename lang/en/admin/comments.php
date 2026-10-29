<?php

declare(strict_types=1);

/**
 * Texts of the standalone Comments detail, replies, and activity widgets. Comment content stays user-authored.
 */
return [
    'resource' => [
        'model_label' => 'comment',
        'plural_model_label' => 'comments',
    ],

    'infolist' => [
        'details' => [
            'heading' => 'Comment details',
            'description' => 'Author and publication details for this comment.',
        ],
        'content' => [
            'heading' => 'Comment content',
            'description' => 'The complete message in this conversation.',
        ],
    ],

    'fields' => [
        'author' => 'Author',
        'created_at' => 'Created at',
        'message' => 'Message',
        'edited_at' => 'Edited at',
        'replying_to' => 'Replying to',
    ],

    'replies' => [
        'table' => [
            'reply' => 'Reply',
        ],
        'filters' => [
            'creation_date' => 'Creation date',
        ],
        'actions' => [
            'view' => 'View reply',
            'modal_heading' => 'Reply details',
            'close' => 'Close',
        ],
        'modal' => [
            'content_heading' => 'Reply content',
            'details_description' => 'Author and timeline for this reply.',
        ],
    ],

    'activity' => [
        'replies' => 'Replies',
        'replies_description' => 'Replies to this comment',
        'likes' => 'Likes',
        'likes_description' => 'Likes for this comment',
    ],
];

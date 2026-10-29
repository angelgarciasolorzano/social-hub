<?php

declare(strict_types=1);

/**
 * Texts of the Posts section of the admin panel. Post and comment content remains user-authored data.
 */
return [
    'resource' => [
        'model_label' => 'post',
        'plural_model_label' => 'posts',
        'navigation_label' => 'Posts',
    ],

    'table' => [
        'image' => 'Image',
        'image_alt' => 'Post image',
        'post' => 'Post',
        'author' => 'Author',
        'created_at' => 'Created at',
    ],

    'filters' => [
        'comment_presence' => 'Comment presence',
        'all_posts' => 'All posts',
        'has_comments' => 'Has comments',
        'no_comments' => 'No comments',
        'creation_date' => 'Creation date',
    ],

    'detail' => [
        'information' => 'Post information',
        'information_description' => 'Author and history of this post.',
        'author' => 'Author',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'content' => 'Post content',
        'content_description' => 'The complete message and image shared in this post.',
        'message' => 'Message',
        'image' => 'Post image',
        'image_alt' => 'Post image',
    ],

    'comments' => [
        'fields' => [
            'content' => 'Content',
            'author' => 'Author',
            'created_at' => 'Created at',
        ],
        'filters' => [
            'creation_date' => 'Creation date',
            'author' => 'Author',
            'reply_presence' => 'Reply presence',
            'all_comments' => 'All comments',
            'has_replies' => 'Has replies',
            'no_replies' => 'No replies',
        ],
    ],

    'widgets' => [
        'overview' => [
            'heading' => 'Posts overview',
            'posts' => 'Posts',
            'posts_description' => 'New posts per day over the last 7 days',
            'comments' => 'Comments',
            'comments_description' => 'New comments on posts per day over the last 7 days',
            'likes' => 'Likes',
            'likes_description' => 'New likes on posts per day over the last 7 days',
        ],
        'activity' => [
            'comments' => 'Comments',
            'comments_description' => 'New comments per day over the last 7 days',
            'replies' => 'Replies',
            'replies_description' => 'New replies per day over the last 7 days',
            'likes' => 'Likes',
            'likes_description' => 'New likes per day over the last 7 days',
        ],
    ],
];

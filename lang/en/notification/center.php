<?php

declare(strict_types=1);

/**
 * Texts for the notification center and localized notification summaries.
 */
return [
    'page' => [
        'title' => 'Notifications',
        'description' => 'Keep up with comments, replies, and likes on your content.',
    ],

    'bell' => [
        'open' => 'Open notifications',
        'open_unread' => '{0}Open notifications|{1}Open notifications, :count unread notification|[2,*]Open notifications, :count unread notifications',
        'unread_count' => '{0}No unread notifications|{1}:count unread notification|[2,*]:count unread notifications',
    ],

    'dropdown' => [
        'title' => 'Recent notifications',
        'view_all' => 'View all notifications',
        'empty_title' => 'You are all caught up',
        'empty_description' => 'New comments, replies, and likes will appear here.',
    ],

    'history' => [
        'all' => 'All',
        'description' => 'Review comments, replies, and likes related to your content.',
        'empty_title' => 'No notifications yet',
        'empty_description' => 'When someone comments, replies, or likes your content, you will see it here.',
        'empty_unread_title' => 'No unread notifications',
        'empty_unread_description' => 'You have read all your notifications.',
        'loading' => 'Loading notifications',
        'load_more' => 'Load more notifications',
        'list_label' => 'Your notifications',
        'unread' => 'Unread',
    ],

    'actions' => [
        'mark_all_read' => 'Mark all as read',
        'mark_read' => 'Mark as read',
        'open_post' => 'Open related post',
        'open_comment' => 'Open related comment',
        'view_all' => 'View all notifications',
    ],

    'status' => [
        'unread' => 'Unread',
        'read' => 'Read',
        'marking_read' => 'Marking as read…',
    ],

    'toasts' => [
        'new_notification' => 'New notification',
        'marked_read' => 'Notification marked as read.',
        'all_marked_read' => 'All notifications marked as read.',
    ],

    'target' => [
        'post_unavailable' => 'This post is no longer available.',
        'comment_unavailable' => 'This comment is no longer available.',
        'content_unavailable' => 'The original content is no longer available.',
    ],

    'summaries' => [
        'comment_on_post' => ':actor commented on your post',
        'reply_to_comment' => ':actor replied to your comment',
        'liked_post' => '{0}No one liked your post|{1}:actor liked your post|{2}:actor and one other person liked your post|[3,*]:actor and :others others liked your post',
        'liked_comment' => '{0}No one liked your comment|{1}:actor liked your comment|{2}:actor and one other person liked your comment|[3,*]:actor and :others others liked your comment',
    ],
];

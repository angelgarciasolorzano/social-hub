<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Listeners;

use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Comment\Events\CommentCreated;
use App\Modules\Comment\Events\CommentDeleted;
use App\Modules\Comment\Events\CommentUpdated;
use App\Modules\Post\Events\PostCreated;
use App\User\Models\User;

/**
 * Records posts and comments in the `content` log by reference only:
 * the text of a post or comment is never stored.
 */
final readonly class ContentActivitySubscriber
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            PostCreated::class => 'handlePostCreated',
            CommentCreated::class => 'handleCommentCreated',
            CommentUpdated::class => 'handleCommentUpdated',
            CommentDeleted::class => 'handleCommentDeleted',
        ];
    }

    public function handlePostCreated(PostCreated $postCreated): void
    {
        $author = $postCreated->post->user;

        $this->activityLogger->record(
            ActivityLogContentEvent::PostCreated,
            $postCreated->post,
            $author instanceof User ? $author : null,
        );
    }

    /**
     * A top-level comment always belongs to a post, so it keeps the post id;
     * a reply keeps its thread and the comment it answered.
     */
    public function handleCommentCreated(CommentCreated $commentCreated): void
    {
        $comment = $commentCreated->comment;

        if ($commentCreated->repliedToCommentId === null) {
            $this->activityLogger->record(
                ActivityLogContentEvent::CommentCreated,
                $comment,
                $commentCreated->user,
                ['post_id' => $comment->commentable_id],
            );

            return;
        }

        $this->activityLogger->record(
            ActivityLogContentEvent::CommentReplied,
            $comment,
            $commentCreated->user,
            [
                'thread_comment_id' => $comment->commentable_id,
                'replied_to_comment_id' => $commentCreated->repliedToCommentId,
            ],
        );
    }

    public function handleCommentUpdated(CommentUpdated $commentUpdated): void
    {
        $this->activityLogger->record(
            ActivityLogContentEvent::CommentUpdated,
            $commentUpdated->comment,
            $commentUpdated->user,
        );
    }

    public function handleCommentDeleted(CommentDeleted $commentDeleted): void
    {
        $this->activityLogger->record(
            ActivityLogContentEvent::CommentDeleted,
            $commentDeleted->comment,
            $commentDeleted->user,
            ['permanently' => $commentDeleted->permanently],
        );
    }
}

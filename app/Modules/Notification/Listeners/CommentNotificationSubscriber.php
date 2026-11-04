<?php

declare(strict_types=1);

namespace App\Modules\Notification\Listeners;

use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Events\CommentCreated;
use App\Modules\Comment\Models\Comment;
use App\Modules\Notification\Notifications\CommentedOnPostNotification;
use App\Modules\Notification\Notifications\RepliedToCommentNotification;
use App\Modules\Post\Models\Post;
use App\User\Models\User;

final readonly class CommentNotificationSubscriber
{
    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            CommentCreated::class => 'handleCommentCreated',
        ];
    }

    public function handleCommentCreated(CommentCreated $commentCreated): void
    {
        if ($commentCreated->repliedToCommentId !== null) {
            $this->notifyReplyRecipient($commentCreated);

            return;
        }

        $this->notifyPostOwner($commentCreated);
    }

    private function notifyPostOwner(CommentCreated $commentCreated): void
    {
        $comment = $commentCreated->comment;
        $post = $comment->commentable;

        if (! $post instanceof Post) {
            return;
        }

        $recipient = $post->user;

        if (! $recipient instanceof User || $recipient->is($commentCreated->user)) {
            return;
        }

        $recipient->notify(new CommentedOnPostNotification(
            actorId: $commentCreated->user->id,
            actorName: $commentCreated->user->name,
            postId: $post->id,
            commentId: $comment->id,
        ));
    }

    private function notifyReplyRecipient(CommentCreated $commentCreated): void
    {
        $reply = $commentCreated->comment;

        if ($reply->commentable_type !== CommentType::COMMENT) {
            return;
        }

        $threadComment = Comment::withTrashed()->find($reply->commentable_id);

        if (! $threadComment instanceof Comment) {
            return;
        }

        if ($reply->replied_to_user_id !== null) {
            $recipient = $reply->repliedToUser;
        } else {
            if ($commentCreated->repliedToCommentId !== $threadComment->id) {
                return;
            }

            $recipient = $threadComment->user;
        }

        if (! $recipient instanceof User || $recipient->is($commentCreated->user)) {
            return;
        }

        $post = $threadComment->commentable;

        if (! $post instanceof Post) {
            return;
        }

        $recipient->notify(new RepliedToCommentNotification(
            actorId: $commentCreated->user->id,
            actorName: $commentCreated->user->name,
            postId: $post->id,
            commentId: $threadComment->id,
        ));
    }
}

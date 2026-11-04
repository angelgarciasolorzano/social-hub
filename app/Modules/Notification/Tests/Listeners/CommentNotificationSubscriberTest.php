<?php

declare(strict_types=1);

use App\Modules\Comment\Events\CommentCreated;
use App\Modules\Notification\Notifications\CommentedOnPostNotification;
use App\Modules\Notification\Notifications\RepliedToCommentNotification;
use Illuminate\Support\Facades\Notification;

it('notifies the post owner when another user comments on their post', function (): void {
    $user = createUser();
    $commentAuthor = createUser();
    $post = createPost($user);
    $comment = createCommentOn($post, $commentAuthor);

    Notification::fake();

    event(new CommentCreated($comment, $commentAuthor));

    Notification::assertSentToOnce($user, CommentedOnPostNotification::class);
    Notification::assertSentTimes(CommentedOnPostNotification::class, 1);
});

it('notifies the root comment author when another user replies to it', function (): void {
    $user = createUser();
    $rootCommentAuthor = createUser();
    $replyAuthor = createUser();
    $post = createPost($user);
    $threadComment = createCommentOn($post, $rootCommentAuthor);
    $comment = createCommentOn($threadComment, $replyAuthor);

    Notification::fake();

    event(new CommentCreated($comment, $replyAuthor, $threadComment->id));

    Notification::assertSentToOnce($rootCommentAuthor, RepliedToCommentNotification::class);
    Notification::assertSentTimes(RepliedToCommentNotification::class, 1);
});

it('notifies the saved replied-to user when someone replies to a reply', function (): void {
    $user = createUser();
    $rootCommentAuthor = createUser();
    $repliedToUser = createUser();
    $replyAuthor = createUser();
    $post = createPost($user);
    $threadComment = createCommentOn($post, $rootCommentAuthor);
    $repliedToComment = createCommentOn($threadComment, $repliedToUser);
    $comment = createCommentOn($threadComment, $replyAuthor, [
        'replied_to_user_id' => $repliedToUser->id,
    ]);

    Notification::fake();

    event(new CommentCreated($comment, $replyAuthor, $repliedToComment->id));

    Notification::assertSentToOnce($repliedToUser, RepliedToCommentNotification::class);
    Notification::assertSentTimes(RepliedToCommentNotification::class, 1);
});

it('does not notify users about their own comments or replies', function (): void {
    $user = createUser();
    $post = createPost($user);
    $ownRootComment = createCommentOn($post, $user);

    $otherPost = createPost();
    $ownThreadComment = createCommentOn($otherPost, $user);
    $comment = createCommentOn($ownThreadComment, $user);
    $ownReplyToReply = createCommentOn($ownThreadComment, $user, [
        'replied_to_user_id' => $user->id,
    ]);

    Notification::fake();

    event(new CommentCreated($ownRootComment, $user));
    event(new CommentCreated($comment, $user, $ownThreadComment->id));
    event(new CommentCreated($ownReplyToReply, $user, $comment->id));

    Notification::assertNothingSent();
});

it('does not fall back to the root author when a reply has no saved recipient', function (): void {
    $user = createUser();
    $rootCommentAuthor = createUser();
    $repliedToUser = createUser();
    $replyAuthor = createUser();
    $post = createPost($user);
    $threadComment = createCommentOn($post, $rootCommentAuthor);
    $repliedToComment = createCommentOn($threadComment, $repliedToUser);
    $comment = createCommentOn($threadComment, $replyAuthor);

    Notification::fake();

    event(new CommentCreated($comment, $replyAuthor, $repliedToComment->id));

    Notification::assertNothingSent();
});

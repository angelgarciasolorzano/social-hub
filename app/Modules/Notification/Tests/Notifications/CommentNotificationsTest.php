<?php

declare(strict_types=1);

use App\Modules\Notification\Notifications\CommentedOnPostNotification;
use App\Modules\Notification\Notifications\RepliedToCommentNotification;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use Illuminate\Support\Facades\Notification;

it('queues a comment-on-post notification through database and broadcast with a stable type', function (): void {
    Notification::fake();

    $user = createUser();
    $actor = createUser();
    $post = createPost($user);
    $comment = createCommentOn($post, $actor);
    $notification = new CommentedOnPostNotification($actor->id, $actor->name, $post->id, $comment->id);

    $user->notify($notification);

    Notification::assertSentTo(
        $user,
        CommentedOnPostNotification::class,
        fn (CommentedOnPostNotification $commentedOnPostNotification): bool => $commentedOnPostNotification->via($user) === ['database', 'broadcast']
            && $commentedOnPostNotification->databaseType($user) === 'comment_on_post'
            && $commentedOnPostNotification->broadcastType() === 'comment_on_post',
    );
});

it('queues a comment-reply notification through database and broadcast with a stable type', function (): void {
    Notification::fake();

    $user = createUser();
    $actor = createUser();
    $post = createPost($user);
    $threadComment = createCommentOn($post, $user);
    $notification = new RepliedToCommentNotification($actor->id, $actor->name, $post->id, $threadComment->id);

    $user->notify($notification);

    Notification::assertSentTo(
        $user,
        RepliedToCommentNotification::class,
        fn (RepliedToCommentNotification $repliedToCommentNotification): bool => $repliedToCommentNotification->via($user) === ['database', 'broadcast']
            && $repliedToCommentNotification->databaseType($user) === 'comment_reply'
            && $repliedToCommentNotification->broadcastType() === 'comment_reply',
    );

});

it('serializes a minimal localized comment-on-post payload after its comment is deleted', function (PreferredLocale $preferredLocale): void {
    $user = createUser();
    $user->forceFill(['preferred_locale' => $preferredLocale])->save();

    $actor = createUser();
    $post = createPost($user);
    $comment = createCommentOn($post, $actor);
    $notification = new CommentedOnPostNotification($actor->id, $actor->name, $post->id, $comment->id);

    $comment->delete();
    app()->setLocale($user->preferredLocale());

    expect($notification->toArray($user))->toBe([
        'summary' => TranslatedText::of('notification/center.summaries.comment_on_post', [
            'actor' => $actor->name,
        ]),
        'actors' => [
            [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
        ],
        'target' => [
            'postId' => $post->id,
            'commentId' => $comment->id,
        ],
    ]);

    $this->assertSoftDeleted($comment);
})->with([
    'English recipient' => [PreferredLocale::ENGLISH],
    'Spanish recipient' => [PreferredLocale::SPANISH],
]);

it('serializes a minimal localized reply payload after its thread comment is deleted', function (PreferredLocale $preferredLocale): void {
    $user = createUser();
    $user->forceFill(['preferred_locale' => $preferredLocale])->save();

    $actor = createUser();
    $post = createPost($user);
    $threadComment = createCommentOn($post, $user);
    $notification = new RepliedToCommentNotification($actor->id, $actor->name, $post->id, $threadComment->id);

    $threadComment->delete();
    app()->setLocale($user->preferredLocale());

    expect($notification->toArray($user))->toBe([
        'summary' => TranslatedText::of('notification/center.summaries.reply_to_comment', [
            'actor' => $actor->name,
        ]),
        'actors' => [
            [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
        ],
        'target' => [
            'postId' => $post->id,
            'commentId' => $threadComment->id,
        ],
    ]);

    $this->assertSoftDeleted($threadComment);
})->with([
    'English recipient' => [PreferredLocale::ENGLISH],
    'Spanish recipient' => [PreferredLocale::SPANISH],
]);

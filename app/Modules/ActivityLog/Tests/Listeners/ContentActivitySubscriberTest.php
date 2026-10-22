<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

const CONTENT_ACTIVITY_SENTINEL_TEXT = 'Texto privado que nunca debe quedar en el registro';

/**
 * The only activity recorded in the `content` log for the given event.
 */
function soleContentActivity(string $eventName): Activity
{
    return Activity::query()
        ->where('log_name', 'content')
        ->where('event', $eventName)
        ->sole();
}

/**
 * Comment on the given post or comment as the given user, the way the frontend does.
 */
function commentForActivityLog(TestCase $testCase, User $user, Post|Comment $commentable): void
{
    $testCase->actingAs($user)->post(route('comments.store'), [
        'content' => CONTENT_ACTIVITY_SENTINEL_TEXT,
        'commentable_type' => $commentable->getMorphClass(),
        'commentable_id' => $commentable->id,
    ])->assertSessionHasNoErrors();
}

it('records a published post without its content', function (): void {
    $user = createUser();

    $this->actingAs($user)->post(route('post.store'), ['content' => CONTENT_ACTIVITY_SENTINEL_TEXT]);

    $activity = soleContentActivity('content.post_created');

    expect($activity)
        ->subject_type->toBe('post')
        ->causer_id->toBe($user->id)
        ->and($activity->subject)->toBeInstanceOf(Post::class)
        ->and($activity->withoutRelations()->toJson())->not->toContain(CONTENT_ACTIVITY_SENTINEL_TEXT);
});

it('records a comment on a post with the post id', function (): void {
    $user = createUser();
    $post = createPost();

    commentForActivityLog($this, $user, $post);

    $activity = soleContentActivity('content.comment_created');

    expect($activity)
        ->subject_type->toBe('comment')
        ->causer_id->toBe($user->id)
        ->and($activity->properties?->get('post_id'))->toBe($post->id)
        ->and($activity->withoutRelations()->toJson())->not->toContain(CONTENT_ACTIVITY_SENTINEL_TEXT);
});

it('records a reply to a root comment with its thread', function (): void {
    $rootComment = createComment();

    commentForActivityLog($this, createUser(), $rootComment);

    expect(soleContentActivity('content.comment_replied')->properties?->only(['thread_comment_id', 'replied_to_comment_id'])->all())
        ->toBe(['thread_comment_id' => $rootComment->id, 'replied_to_comment_id' => $rootComment->id]);
});

it('records a reply to a reply under the root thread and the answered reply', function (): void {
    $rootComment = createComment();
    $comment = createCommentOn($rootComment);

    commentForActivityLog($this, createUser(), $comment);

    expect(soleContentActivity('content.comment_replied')->properties?->only(['thread_comment_id', 'replied_to_comment_id'])->all())
        ->toBe(['thread_comment_id' => $rootComment->id, 'replied_to_comment_id' => $comment->id]);
});

it('records an edit that changes the content', function (): void {
    $user = createUser();
    $comment = createComment($user);

    $this->actingAs($user)->patch(route('comments.update', $comment), ['content' => CONTENT_ACTIVITY_SENTINEL_TEXT]);

    $activity = soleContentActivity('content.comment_updated');

    expect($activity->subject_id)->toBe($comment->id)
        ->and($activity->withoutRelations()->toJson())->not->toContain(CONTENT_ACTIVITY_SENTINEL_TEXT);
});

it('does not record an edit that keeps the same content', function (): void {
    $user = createUser();
    $comment = createComment($user, ['content' => CONTENT_ACTIVITY_SENTINEL_TEXT]);

    $this->actingAs($user)->patch(route('comments.update', $comment), ['content' => CONTENT_ACTIVITY_SENTINEL_TEXT]);

    expect(Activity::query()->where('event', 'content.comment_updated')->exists())->toBeFalse();
});

it('records a comment with replies that becomes a tombstone and still resolves', function (): void {
    $user = createUser();
    $comment = createComment($user);
    createCommentOn($comment);

    $this->actingAs($user)->delete(route('comments.destroy', $comment));

    $activity = soleContentActivity('content.comment_deleted');

    expect($activity->properties?->get('permanently'))->toBeFalse()
        ->and($activity->subject?->is($comment))->toBeTrue();

    $this->assertSoftDeleted($comment);
});

it('keeps the id of a permanently deleted comment', function (): void {
    $user = createUser();
    $comment = createComment($user);

    $this->actingAs($user)->delete(route('comments.destroy', $comment));

    $activity = soleContentActivity('content.comment_deleted');

    expect($activity)
        ->subject_id->toBe($comment->id)
        ->subject->toBeNull()
        ->and($activity->properties?->get('permanently'))->toBeTrue();
});

it('does not record a delete that the policy forbids', function (): void {
    $comment = createComment();

    $this->actingAs(createUser())->delete(route('comments.destroy', $comment))->assertForbidden();

    expect(Activity::query()->where('log_name', 'content')->exists())->toBeFalse();
});

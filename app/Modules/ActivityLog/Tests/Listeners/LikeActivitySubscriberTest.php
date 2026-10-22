<?php

declare(strict_types=1);

use Spatie\Activitylog\Models\Activity;

/**
 * The `likes` activities as [event, subject type, subject id, causer id], oldest first.
 *
 * @return array<int, array{string|null, string|null, int|null, int|null}>
 */
function recordedLikeActivities(): array
{
    return Activity::query()
        ->where('log_name', 'likes')
        ->oldest('id')
        ->get()
        ->map(fn (Activity $activity): array => [$activity->event, $activity->subject_type, $activity->subject_id, $activity->causer_id])
        ->values()
        ->all();
}

it('records adding and removing a like on a post', function (): void {
    $user = createUser();
    $post = createPost();

    $this->actingAs($user)->postJson(route('posts.like', $post))->assertOk();
    $this->actingAs($user)->postJson(route('posts.like', $post))->assertOk();

    expect(recordedLikeActivities())->toBe([
        ['likes.added', 'post', $post->id, $user->id],
        ['likes.removed', 'post', $post->id, $user->id],
    ]);
});

it('records adding a like on a comment', function (): void {
    $user = createUser();
    $comment = createComment();

    $this->actingAs($user)->postJson(route('comments.like', $comment))->assertOk();

    expect(recordedLikeActivities())->toBe([
        ['likes.added', 'comment', $comment->id, $user->id],
    ]);
});

it('does not record a like attempt from a guest', function (): void {
    $post = createPost();

    $this->postJson(route('posts.like', $post))->assertUnauthorized();

    expect(recordedLikeActivities())->toBeEmpty();
});

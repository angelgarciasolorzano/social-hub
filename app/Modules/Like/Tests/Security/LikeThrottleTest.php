<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Toggle the like of the given comment as the given user.
 *
 * @return TestResponse<Response>
 */
function throttledToggleAs(TestCase $testCase, User $user, Comment $comment): TestResponse
{
    return $testCase->actingAs($user)->postJson(route('comments.like', $comment));
}

it('allows 30 toggles per minute and then answers 429 with Retry-After', function (): void {
    $user = createUser();
    $comment = createComment();

    foreach (range(1, 30) as $attempt) {
        throttledToggleAs($this, $user, $comment)->assertOk();
    }

    throttledToggleAs($this, $user, $comment)
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('lets the user toggle again once the minute has passed', function (): void {
    $user = createUser();
    $comment = createComment();

    foreach (range(1, 30) as $attempt) {
        throttledToggleAs($this, $user, $comment);
    }

    throttledToggleAs($this, $user, $comment)->assertTooManyRequests();

    $this->travel(61)->seconds();

    throttledToggleAs($this, $user, $comment)->assertOk();
});

it('throttles each user independently', function (): void {
    $comment = createComment();
    $user = createUser();
    $bystander = createUser();

    foreach (range(1, 31) as $attempt) {
        throttledToggleAs($this, $user, $comment);
    }

    throttledToggleAs($this, $user, $comment)->assertTooManyRequests();
    throttledToggleAs($this, $bystander, $comment)->assertOk();
});

it('does not share its budget with publishing comments', function (): void {
    $user = createUser();
    $comment = createComment();

    foreach (range(1, 30) as $attempt) {
        throttledToggleAs($this, $user, $comment);
    }

    $this->actingAs($user)->post(route('comments.store'), [
        'content' => 'Un comentario',
        'commentable_type' => Comment::MORPH_NAME,
        'commentable_id' => $comment->id,
    ])->assertRedirect();
});

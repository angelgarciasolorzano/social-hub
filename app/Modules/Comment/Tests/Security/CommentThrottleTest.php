<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Send a comment on the given post as the given user.
 *
 * @return TestResponse<Response>
 */
function storeCommentAs(TestCase $testCase, User $user, Post $post): TestResponse
{
    return $testCase->actingAs($user)->post(route('comments.store'), [
        'content' => 'Un comentario',
        'commentable_type' => Post::MORPH_NAME,
        'commentable_id' => $post->id,
    ]);
}

it('allows 6 comments per minute and then answers 429', function (): void {
    $user = createUser();
    $post = createPost();

    foreach (range(1, 6) as $attempt) {
        storeCommentAs($this, $user, $post)->assertRedirect();
    }

    storeCommentAs($this, $user, $post)->assertTooManyRequests();

    expect(Comment::query()->count())->toBe(6);
});

it('lets the user comment again once the minute has passed', function (): void {
    $user = createUser();
    $post = createPost();

    foreach (range(1, 6) as $attempt) {
        storeCommentAs($this, $user, $post);
    }

    storeCommentAs($this, $user, $post)->assertTooManyRequests();

    $this->travel(61)->seconds();

    storeCommentAs($this, $user, $post)->assertRedirect();

    expect(Comment::query()->count())->toBe(7);
});

it('throttles each user independently', function (): void {
    $post = createPost();
    $user = createUser();
    $bystander = createUser();

    foreach (range(1, 7) as $attempt) {
        storeCommentAs($this, $user, $post);
    }

    storeCommentAs($this, $user, $post)->assertTooManyRequests();
    storeCommentAs($this, $bystander, $post)->assertRedirect();
});

it('does not let listing comments consume the comment publishing budget', function (): void {
    $user = createUser();
    $post = createPost();

    foreach (range(1, 20) as $attempt) {
        $this->actingAs($user)
            ->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => $post->id]))
            ->assertRedirect();
    }

    storeCommentAs($this, $user, $post)->assertRedirect();
});

it('allows 60 listings per minute and then answers 429', function (): void {
    $user = createUser();
    $post = createPost();

    foreach (range(1, 60) as $attempt) {
        $this->actingAs($user)
            ->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => $post->id]))
            ->assertRedirect();
    }

    $this->actingAs($user)
        ->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => $post->id]))
        ->assertTooManyRequests();
});

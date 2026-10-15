<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Props\CommentThreadProps;
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

/**
 * Send a comment on the given post, comment or reply as the given user.
 *
 * @return TestResponse<Response>
 */
function storeCommentOn(TestCase $testCase, User $user, Post|Comment $commentable): TestResponse
{
    return $testCase->actingAs($user)->post(route('comments.store'), [
        'content' => 'Un comentario',
        'commentable_type' => $commentable instanceof Post ? Post::MORPH_NAME : Comment::MORPH_NAME,
        'commentable_id' => $commentable->id,
    ]);
}

/**
 * Ask the home page for the comments of the given post the way the dialog does, as the given user.
 *
 * @return TestResponse<Response>
 */
function loadCommentsAs(TestCase $testCase, User $user, Post $post): TestResponse
{
    $testCase->actingAs($user);

    return $testCase->get(
        route('home', [CommentThreadProps::COMMENTS_PARAMETER => $post->id]),
        inertiaPartialHeaders($testCase, 'home/Home', 'comments'),
    );
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

it('shares the publishing budget between posts, comments and replies', function (): void {
    $user = createUser();
    $post = createPost();
    $comment = createCommentOn($post);
    $reply = createCommentOn($comment);

    foreach ([$post, $comment, $reply, $post, $comment, $reply] as $commentable) {
        storeCommentOn($this, $user, $commentable)->assertRedirect();
    }

    storeCommentOn($this, $user, $reply)->assertTooManyRequests();
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
        loadCommentsAs($this, $user, $post)->assertOk();
    }

    storeCommentAs($this, $user, $post)->assertRedirect();
});

it('does not let publishing comments consume the comment reading budget', function (): void {
    $user = createUser();
    $post = createPost();

    foreach (range(1, 6) as $attempt) {
        storeCommentAs($this, $user, $post)->assertRedirect();
    }

    storeCommentAs($this, $user, $post)->assertTooManyRequests();

    loadCommentsAs($this, $user, $post)->assertOk();
});

it('allows 60 listings per minute and then answers 429', function (): void {
    $user = createUser();
    $post = createPost();

    foreach (range(1, 60) as $attempt) {
        loadCommentsAs($this, $user, $post)->assertOk();
    }

    loadCommentsAs($this, $user, $post)->assertTooManyRequests();
});

it('throttles the reading of comments per user', function (): void {
    $user = createUser();
    $bystander = createUser();
    $post = createPost();

    foreach (range(1, 61) as $attempt) {
        loadCommentsAs($this, $user, $post);
    }

    loadCommentsAs($this, $user, $post)->assertTooManyRequests();
    loadCommentsAs($this, $bystander, $post)->assertOk();
});

it('lets the user read comments again once the minute has passed', function (): void {
    $user = createUser();
    $post = createPost();

    foreach (range(1, 61) as $attempt) {
        loadCommentsAs($this, $user, $post);
    }

    loadCommentsAs($this, $user, $post)->assertTooManyRequests();

    $this->travel(61)->seconds();

    loadCommentsAs($this, $user, $post)->assertOk();
});

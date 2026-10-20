<?php

declare(strict_types=1);

use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Toggle the like of the given post as the given user, the way the frontend does.
 *
 * @return TestResponse<Response>
 */
function togglePostLikeAs(TestCase $testCase, User $user, Post $post): TestResponse
{
    return $testCase->actingAs($user)->postJson(route('posts.like', $post));
}

it('likes a post the user has not liked yet', function (): void {
    $user = createUser();
    $post = createPost();

    togglePostLikeAs($this, $user, $post)
        ->assertOk()
        ->assertExactJson(['liked' => true, 'likesCount' => 1]);

    expect($post->isLikedBy($user))->toBeTrue();
});

it('removes the like when the user toggles a post they already liked', function (): void {
    $user = createUser();
    $post = createPost();
    createLike($user, $post);

    togglePostLikeAs($this, $user, $post)
        ->assertOk()
        ->assertExactJson(['liked' => false, 'likesCount' => 0]);

    expect(Like::query()->count())->toBe(0);
});

it('likes again after the like was removed', function (): void {
    $user = createUser();
    $post = createPost();

    togglePostLikeAs($this, $user, $post);
    togglePostLikeAs($this, $user, $post);
    togglePostLikeAs($this, $user, $post)
        ->assertExactJson(['liked' => true, 'likesCount' => 1]);
});

it('does not change the like of another user', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $post = createPost();
    createLike($otherUser, $post);

    togglePostLikeAs($this, $user, $post)
        ->assertExactJson(['liked' => true, 'likesCount' => 2]);

    expect($post->isLikedBy($otherUser))->toBeTrue();
});

it('likes the post of the user itself', function (): void {
    $user = createUser();
    $post = createPost($user);

    togglePostLikeAs($this, $user, $post)
        ->assertExactJson(['liked' => true, 'likesCount' => 1]);
});

it('keeps the like of the user on a comment when liking a post', function (): void {
    $user = createUser();
    $comment = createComment();
    $post = createPost();

    createLike($user, $comment);

    togglePostLikeAs($this, $user, $post)
        ->assertExactJson(['liked' => true, 'likesCount' => 1]);

    expect(Like::query()->count())->toBe(2);
});

it('answers 404 when the post does not exist', function (): void {
    $this->actingAs(createUser())
        ->postJson(route('posts.like', 999999))
        ->assertNotFound();

    expect(Like::query()->count())->toBe(0);
});

it('rejects guests without creating a like', function (): void {
    $post = createPost();

    $this->postJson(route('posts.like', $post))->assertUnauthorized();

    expect(Like::query()->count())->toBe(0);
});

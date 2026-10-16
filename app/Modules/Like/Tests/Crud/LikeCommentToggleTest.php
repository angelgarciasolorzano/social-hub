<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Toggle the like of the given comment as the given user, the way the frontend does.
 *
 * @return TestResponse<Response>
 */
function toggleLikeAs(TestCase $testCase, User $user, Comment $comment): TestResponse
{
    return $testCase->actingAs($user)->postJson(route('comments.like', $comment));
}

it('likes a comment the user has not liked yet', function (): void {
    $user = createUser();
    $comment = createComment();

    toggleLikeAs($this, $user, $comment)
        ->assertOk()
        ->assertExactJson(['liked' => true, 'likesCount' => 1]);

    expect($comment->isLikedBy($user))->toBeTrue();
});

it('removes the like when the user toggles a comment they already liked', function (): void {
    $user = createUser();
    $comment = createComment();
    createLike($user, $comment);

    toggleLikeAs($this, $user, $comment)
        ->assertOk()
        ->assertExactJson(['liked' => false, 'likesCount' => 0]);

    expect(Like::query()->count())->toBe(0);
});

it('likes again after the like was removed', function (): void {
    $user = createUser();
    $comment = createComment();

    toggleLikeAs($this, $user, $comment);
    toggleLikeAs($this, $user, $comment);
    toggleLikeAs($this, $user, $comment)
        ->assertExactJson(['liked' => true, 'likesCount' => 1]);
});

it('does not change the like of another user', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $comment = createComment();
    createLike($otherUser, $comment);

    toggleLikeAs($this, $user, $comment)
        ->assertExactJson(['liked' => true, 'likesCount' => 2]);

    expect($comment->isLikedBy($otherUser))->toBeTrue();
});

it('likes a reply', function (): void {
    $user = createUser();
    $comment = createCommentOn(createComment());

    toggleLikeAs($this, $user, $comment)
        ->assertExactJson(['liked' => true, 'likesCount' => 1]);
});

it('likes the comment of the user itself', function (): void {
    $user = createUser();
    $comment = createComment($user);

    toggleLikeAs($this, $user, $comment)
        ->assertExactJson(['liked' => true, 'likesCount' => 1]);
});

it('answers 404 when the comment does not exist', function (): void {
    $this->actingAs(createUser())
        ->postJson(route('comments.like', 999999))
        ->assertNotFound();

    expect(Like::query()->count())->toBe(0);
});

it('rejects guests without creating a like', function (): void {
    $comment = createComment();

    $this->postJson(route('comments.like', $comment))->assertUnauthorized();

    expect(Like::query()->count())->toBe(0);
});

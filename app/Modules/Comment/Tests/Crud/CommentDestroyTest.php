<?php

declare(strict_types=1);

use App\Modules\Comment\Database\Factories\CommentFactory;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Delete the given comment as the given user, or as a guest when none is given.
 *
 * @return TestResponse<Response>
 */
function deleteCommentAs(TestCase $testCase, ?User $user, Comment|int $comment): TestResponse
{
    if ($user instanceof User) {
        $testCase->actingAs($user);
    }

    return $testCase->delete(route('comments.destroy', $comment));
}

/**
 * Create a root comment that was already deleted, which only happens when it had replies.
 */
function createDeletedRootComment(User $user): Comment
{
    return CommentFactory::new()->for($user)->trashed()->createOne();
}

it('deletes a comment without replies for good and flashes a success message', function (): void {
    $user = createUser();
    $comment = createComment($user);

    $testResponse = deleteCommentAs($this, $user, $comment);

    $testResponse->assertRedirect();
    $testResponse->assertInertiaFlash('type', 'success');
    $testResponse->assertInertiaFlash('message', 'Comentario eliminado correctamente');

    expect(Comment::withTrashed()->find($comment->id))->toBeNull();
});

it('deletes the likes of a comment deleted for good', function (): void {
    $user = createUser();
    $comment = createComment($user);
    $otherComment = createComment();
    createLike(createUser(), $comment);
    createLike(createUser(), $comment);
    $unrelatedLike = createLike(createUser(), $otherComment);

    deleteCommentAs($this, $user, $comment);

    expect(Like::query()->pluck('id')->all())->toBe([$unrelatedLike->id]);
});

it('keeps a comment with replies as a deleted comment with its replies intact', function (): void {
    $user = createUser();
    $comment = createComment($user);
    $reply = createCommentOn($comment);

    deleteCommentAs($this, $user, $comment)->assertRedirect();

    expect(Comment::withTrashed()->find($comment->id)?->trashed())->toBeTrue()
        ->and(Comment::query()->find($reply->id))->not->toBeNull();
});

it('keeps the likes of a comment that was only marked as deleted', function (): void {
    $user = createUser();
    $comment = createComment($user);
    createCommentOn($comment);
    createLike(createUser(), $comment);

    deleteCommentAs($this, $user, $comment);

    expect($comment->likes()->count())->toBe(1);
});

it('deletes a reply for good and leaves a live parent untouched', function (): void {
    $user = createUser();
    $comment = createComment();
    $reply = createCommentOn($comment, $user);
    createLike(createUser(), $reply);

    deleteCommentAs($this, $user, $reply)->assertRedirect();

    expect(Comment::withTrashed()->find($reply->id))->toBeNull()
        ->and(Like::query()->count())->toBe(0)
        ->and(Comment::query()->find($comment->id)?->trashed())->toBeFalse();
});

it('also deletes a deleted parent when its last reply is deleted', function (): void {
    $user = createUser();
    $comment = createDeletedRootComment(createUser());
    createLike(createUser(), $comment);
    $reply = createCommentOn($comment, $user);

    deleteCommentAs($this, $user, $reply)->assertRedirect();

    expect(Comment::withTrashed()->find($reply->id))->toBeNull()
        ->and(Comment::withTrashed()->find($comment->id))->toBeNull()
        ->and(Like::query()->count())->toBe(0);
});

it('keeps a deleted parent while it still has other replies', function (): void {
    $user = createUser();
    $comment = createDeletedRootComment(createUser());
    $reply = createCommentOn($comment, $user);
    createCommentOn($comment);

    deleteCommentAs($this, $user, $reply)->assertRedirect();

    expect(Comment::withTrashed()->find($comment->id)?->trashed())->toBeTrue();
});

it('answers 403 to another user and deletes nothing', function (): void {
    $comment = createComment(createUser());
    createLike(createUser(), $comment);

    deleteCommentAs($this, createUser(), $comment)->assertForbidden();

    expect(Comment::query()->find($comment->id))->not->toBeNull()
        ->and(Like::query()->count())->toBe(1);
});

it('redirects guests to the login page without deleting', function (): void {
    $comment = createComment();

    deleteCommentAs($this, null, $comment)->assertRedirect(route('login'));

    expect(Comment::query()->find($comment->id))->not->toBeNull();
});

it('answers 404 when the comment does not exist', function (): void {
    deleteCommentAs($this, createUser(), 999999)->assertNotFound();
});

it('answers 404 when the comment was already deleted', function (): void {
    $user = createUser();
    $comment = createDeletedRootComment($user);

    deleteCommentAs($this, $user, $comment->id)->assertNotFound();

    expect(Comment::withTrashed()->find($comment->id))->not->toBeNull();
});

it('keeps the likes when deleting the comment fails halfway', function (): void {
    $user = createUser();
    $comment = createComment($user);
    createLike(createUser(), $comment);

    Comment::deleting(static fn (): never => throw new RuntimeException('The delete failed.'));

    try {
        $this->withoutExceptionHandling();

        expect(fn (): TestResponse => deleteCommentAs($this, $user, $comment))->toThrow(RuntimeException::class);
    } finally {
        Comment::flushEventListeners();
    }

    expect(Comment::query()->find($comment->id))->not->toBeNull()
        ->and(Like::query()->count())->toBe(1);
});

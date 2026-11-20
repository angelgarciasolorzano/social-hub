<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A post of the given user with an image, a like, a comment with a like, a reply with a like and
 * a deleted comment that keeps a reply.
 *
 * @return array{post: Post, imagePath: string, commentIds: list<int>, likeIds: list<int>}
 */
function postWithEngagementOf(User $user): array
{
    $post = createPost($user);
    $media = $post->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $comment = createCommentOn($post);
    $reply = createCommentOn($comment);
    $deletedComment = createCommentOn($post);
    $replyOfDeletedComment = createCommentOn($deletedComment);
    $deletedComment->delete();

    $postLike = createLike(null, $post);
    $commentLike = createLike(null, $comment);
    $replyLike = createLike(null, $reply);

    return [
        'post' => $post,
        'imagePath' => $media->getPathRelativeToRoot(),
        'commentIds' => [$comment->id, $reply->id, $deletedComment->id, $replyOfDeletedComment->id],
        'likeIds' => [$postLike->id, $commentLike->id, $replyLike->id],
    ];
}

/**
 * Delete the account of the given user, confirming it with the given password.
 */
function deleteAccountOf(TestCase $testCase, User $user, string $password): void
{
    $testCase->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('user.destroy', $user), ['password' => $password]);
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('deletes the posts of the account with their likes, comments, replies and images', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = createUser();
    $firstPost = postWithEngagementOf($user);
    $secondPost = postWithEngagementOf($user);

    deleteAccountOf($testCase, $user, 'password');

    foreach ([$firstPost, $secondPost] as $deletedPost) {
        expect(Post::query()->whereKey($deletedPost['post']->id)->exists())->toBeFalse()
            ->and(Comment::withTrashed()->whereKey($deletedPost['commentIds'])->exists())->toBeFalse()
            ->and(Like::query()->whereKey($deletedPost['likeIds'])->exists())->toBeFalse();

        Storage::disk('public')->assertMissing($deletedPost['imagePath']);
    }

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('keeps the comments and likes the deleted account left on the posts of others', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = createUser();
    $otherPost = postWithEngagementOf(createUser());
    $commentOnOtherPost = createCommentOn($otherPost['post'], $user);
    $likeOnOtherPost = createLike($user, $otherPost['post']);

    deleteAccountOf($testCase, $user, 'password');

    expect(Post::query()->whereKey($otherPost['post']->id)->exists())->toBeTrue()
        ->and(Comment::withTrashed()->whereKey($otherPost['commentIds'])->count())->toBe(4)
        ->and(Like::query()->whereKey($otherPost['likeIds'])->count())->toBe(3)
        ->and(Comment::query()->whereKey($commentOnOtherPost->id)->exists())->toBeTrue()
        ->and(Like::query()->whereKey($likeOnOtherPost->id)->exists())->toBeTrue();

    Storage::disk('public')->assertExists($otherPost['imagePath']);
});

it('deletes nothing when the password is wrong', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = createUser();
    $post = postWithEngagementOf($user);

    deleteAccountOf($testCase, $user, 'wrong-password');

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(Post::query()->whereKey($post['post']->id)->exists())->toBeTrue()
        ->and(Comment::withTrashed()->whereKey($post['commentIds'])->count())->toBe(4)
        ->and(Like::query()->whereKey($post['likeIds'])->count())->toBe(3);

    Storage::disk('public')->assertExists($post['imagePath']);
});

<?php

declare(strict_types=1);

use App\Modules\Comment\Database\Factories\CommentFactory;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Load the post with its engagement preloaded for the given viewer.
 */
function postWithEngagementFor(Post $post, User $user): Post
{
    return Post::query()->withEngagementFor($user)->findOrFail($post->id);
}

it('belongs to the user who created it', function (): void {
    $user = createUser();
    $post = createPost($user);

    expect($post->user?->id)->toBe($user->id)
        ->and($user->posts()->pluck('id')->all())->toBe([$post->id]);
});

it('owns the comments and likes attached to it', function (): void {
    $user = createUser();
    $post = createPost($user);
    $otherPost = createPost($user);

    $comment = $post->comments()->create(['user_id' => $user->id, 'content' => 'Un comentario']);
    $like = new Like;
    $like->forceFill(['user_id' => $user->id]);
    $post->likes()->save($like);

    $otherPost->comments()->create(['user_id' => $user->id, 'content' => 'Comentario ajeno']);

    expect($post->comments->pluck('id')->all())->toBe([$comment->id])
        ->and($post->likes->pluck('id')->all())->toBe([$like->id])
        ->and($comment->commentable_type)->toBe(CommentType::POST);
});

it('is registered in the morph map under the post key', function (): void {
    expect(Post::MORPH_NAME)->toBe('post')
        ->and(Relation::getMorphedModel('post'))->toBe(Post::class)
        ->and((new Post)->getMorphClass())->toBe('post');
});

it('keeps a single image in the posts_images collection', function (): void {
    Storage::fake('public');

    $post = createPost(createUser());

    $post->addMedia(UploadedFile::fake()->image('first.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);
    $post->addMedia(UploadedFile::fake()->image('second.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $media = $post->refresh()->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    expect($media)->toHaveCount(1)
        ->and($media->first()?->file_name)->toBe('second.jpg');
});

it('counts the live root comments plus every reply, even the ones under a deleted comment', function (): void {
    $post = createPost();
    createCommentsOn($post, 2);
    $deletedComment = CommentFactory::new()->trashed()->createOne([
        'commentable_id' => $post->id,
        'commentable_type' => Post::MORPH_NAME,
    ]);
    createCommentsOn($deletedComment, 3);
    createCommentOn(createCommentOn($post));
    createCommentsOn(createPost(), 4);

    expect(postWithEngagementFor($post, createUser())->commentsTotal())->toBe(3 + 4);
});

it('counts zero comments for a post nobody commented on', function (): void {
    $post = createPost();

    expect(postWithEngagementFor($post, createUser())->commentsTotal())->toBe(0);
});

it('preloads the likes and whether the viewer liked the post', function (): void {
    $post = createPost();
    $user = createUser();
    createLike($user, $post);
    createLike(createUser(), $post);
    createLike(createUser(), createPost());

    $loadedPost = postWithEngagementFor($post, $user);

    expect($loadedPost->likesCount())->toBe(2)
        ->and($loadedPost->isLikedBy($user))->toBeTrue();
});

it('does not mark the post as liked for a user who did not like it', function (): void {
    $post = createPost();
    createLike(createUser(), $post);

    expect(postWithEngagementFor($post, createUser())->isLikedBy(createUser()))->toBeFalse();
});

it('preloads the engagement of many posts in a single query', function (): void {
    $user = createUser();

    foreach (range(1, 10) as $attempt) {
        $post = createPost();
        createCommentOn(createCommentOn($post));
        createLike($user, $post);
    }

    DB::enableQueryLog();
    $posts = Post::query()->withEngagementFor($user)->get();
    $queries = DB::connection()->getQueryLog();
    DB::disableQueryLog();

    expect($posts)->toHaveCount(10)
        ->and($queries)->toHaveCount(1);
});

it('gives the same engagement with and without the preloaded attributes', function (): void {
    $post = createPost();
    $user = createUser();
    createCommentOn(createCommentOn($post));
    createLike($user, $post);

    $loadedPost = postWithEngagementFor($post, $user);

    expect($post->commentsTotal())->toBe($loadedPost->commentsTotal())
        ->and($post->likesCount())->toBe($loadedPost->likesCount())
        ->and($post->isLikedBy($user))->toBe($loadedPost->isLikedBy($user));
});

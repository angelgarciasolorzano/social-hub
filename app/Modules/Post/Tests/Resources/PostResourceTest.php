<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use App\Modules\Post\Resources\PostResource;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Serialize the post as the given user (or as a guest) would receive it.
 *
 * @return array<mixed>
 */
function postPayloadAs(Post $post, ?User $user = null): array
{
    $request = Request::create('/');
    $request->setUserResolver(static fn (): ?User => $user);

    return new PostResource($post)->resolve($request);
}

it('serializes only the public post fields', function (): void {
    $post = createPost();

    $payload = new PostResource($post)->toArray(request());

    expect(array_keys($payload))->toBe(['id', 'content', 'image', 'createdAt', 'commentsCount', 'likesInfo', 'user'])
        ->and($payload['id'])->toBe($post->id)
        ->and($payload['content'])->toBe($post->content)
        ->and($payload['createdAt'])->toBe($post->created_at?->toIso8601String());
});

it('returns an empty image when the post has no media', function (): void {
    $post = createPost();

    expect(new PostResource($post)->toArray(request())['image'])->toBe('');
});

it('returns the image url when the post has an image in posts_images', function (): void {
    Storage::fake('public');

    $post = createPost();
    $post->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $image = new PostResource($post->refresh())->toArray(request())['image'];

    expect($image)->toBeString()
        ->and($image)->not->toBe('')
        ->and($image)->toContain('photo.jpg');
});

it('reports the number of comments of the post, counting replies', function (): void {
    $post = createPost();

    expect(postPayloadAs($post))->toMatchArray(['commentsCount' => 0]);

    createCommentOn(createCommentOn($post));
    createCommentOn($post);

    expect(postPayloadAs($post->fresh() ?? $post))->toMatchArray(['commentsCount' => 3]);
});

it('reports the likes of the post and whether the user liked it', function (): void {
    $post = createPost();
    $user = createUser();
    createLike($user, $post);
    createLike(createUser(), $post);

    expect(postPayloadAs($post, $user))->toMatchArray(['likesInfo' => ['count' => 2, 'likedByMe' => true]]);
});

it('reports that another user did not like the post', function (): void {
    $post = createPost();
    createLike(createUser(), $post);

    expect(postPayloadAs($post, createUser()))->toMatchArray(['likesInfo' => ['count' => 1, 'likedByMe' => false]]);
});

it('reports that a guest did not like the post', function (): void {
    $post = createPost();
    createLike(createUser(), $post);

    expect(postPayloadAs($post))->toMatchArray(['likesInfo' => ['count' => 1, 'likedByMe' => false]]);
});

it('serializes the engagement without querying comments or likes when it is preloaded', function (): void {
    $post = createPost();
    $user = createUser();
    createCommentOn(createCommentOn($post));
    createLike($user, $post);
    $loadedPost = Post::query()->withEngagementFor($user)->findOrFail($post->id);

    DB::enableQueryLog();
    $payload = postPayloadAs($loadedPost, $user);
    $queries = DB::connection()->getQueryLog();
    DB::disableQueryLog();

    $engagementQueries = array_filter(
        array_column($queries, 'query'),
        static fn (string $query): bool => str_contains($query, '`comments`') || str_contains($query, '`likes`'),
    );

    expect($payload)->toMatchArray(['commentsCount' => 2, 'likesInfo' => ['count' => 1, 'likedByMe' => true]])
        ->and($engagementQueries)->toBeEmpty();
});

it('serializes the public data of the author and never the email', function (): void {
    $user = createUser();
    $post = createPost($user);

    $payload = postPayloadAs($post->load('user'));

    expect($payload['user'])->toMatchArray(['id' => $user->id, 'name' => $user->name])
        ->and($payload['user'])->toHaveKeys(['id', 'name', 'profilePicture'])
        ->and(json_encode($payload))->not->toContain($user->email);
});

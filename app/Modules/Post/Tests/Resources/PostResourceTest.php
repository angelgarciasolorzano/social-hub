<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use App\Modules\Post\Resources\PostResource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Create a persisted Post, same #[UseFactory] workaround as createUser()
 * in tests/Pest.php.
 */
function createPostForResource(): Post
{
    /** @var Factory<Post> $factory */
    $factory = Post::factory();

    return $factory->createOne();
}

it('serializes only the public post fields', function (): void {
    $post = createPostForResource();

    $payload = (new PostResource($post))->toArray(request());

    expect(array_keys($payload))->toBe(['id', 'content', 'image', 'createdAt'])
        ->and($payload['id'])->toBe($post->id)
        ->and($payload['content'])->toBe($post->content)
        ->and($payload['createdAt'])->toBe($post->created_at?->toIso8601String());
});

it('returns an empty image when the post has no media', function (): void {
    $post = createPostForResource();

    expect((new PostResource($post))->toArray(request())['image'])->toBe('');
});

it('returns the image url when the post has an image in posts_images', function (): void {
    Storage::fake('public');

    $post = createPostForResource();
    $post->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $image = (new PostResource($post->refresh()))->toArray(request())['image'];

    expect($image)->toBeString()
        ->and($image)->not->toBe('')
        ->and($image)->toContain('photo.jpg');
});

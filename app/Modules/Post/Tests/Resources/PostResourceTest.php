<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use App\Modules\Post\Resources\PostResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('serializes only the public post fields', function (): void {
    $post = createPost();

    $payload = new PostResource($post)->toArray(request());

    expect(array_keys($payload))->toBe(['id', 'content', 'image', 'createdAt'])
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

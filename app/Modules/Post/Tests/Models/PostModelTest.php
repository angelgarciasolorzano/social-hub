<?php

declare(strict_types=1);

use App\Comment\Enums\CommentType;
use App\Like\Models\Like;
use App\Modules\Post\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('belongs to the user who created it', function (): void {
    $user = createUser();
    $post = createPostFor($user);

    expect($post->user?->id)->toBe($user->id)
        ->and($user->posts()->pluck('id')->all())->toBe([$post->id]);
});

it('owns the comments and likes attached to it', function (): void {
    $user = createUser();
    $post = createPostFor($user);
    $otherPost = createPostFor($user);

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

    $post = createPostFor(createUser());

    $post->addMedia(UploadedFile::fake()->image('first.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);
    $post->addMedia(UploadedFile::fake()->image('second.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $media = $post->refresh()->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    expect($media)->toHaveCount(1)
        ->and($media->first()?->file_name)->toBe('second.jpg');
});

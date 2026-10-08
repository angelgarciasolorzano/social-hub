<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use App\Support\MediaLibrary\MediaLibraryCustomPathGenerator;
use App\User\Enums\UserImageType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uses the custom path generator from App\Support', function (): void {
    expect(config('media-library.path_generator'))->toBe(MediaLibraryCustomPathGenerator::class);
});

it('stores post images under posts/{media id}/', function (): void {
    $localFilesystemAdapter = Storage::fake('public');

    $post = createPost();
    $media = $post->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $localFilesystemAdapter->assertExists('posts/'.$media->id.'/photo.jpg');
});

it('stores user pictures under {media id}/ without a model folder', function (UserImageType $userImageType): void {
    $localFilesystemAdapter = Storage::fake('public');

    $user = createUser();
    $media = $user->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection($userImageType->value());

    $localFilesystemAdapter->assertExists($media->id.'/photo.jpg');
    $localFilesystemAdapter->assertMissing('posts/'.$media->id.'/photo.jpg');
})->with([
    'profile picture' => UserImageType::PROFILE_PICTURE,
    'cover image' => UserImageType::COVER_IMAGE,
]);

it('keeps post and user files in different folders', function (): void {
    Storage::fake('public');

    $postMedia = createPost()->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);
    $userMedia = createUser()->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection(UserImageType::PROFILE_PICTURE->value());

    expect($postMedia->getPathRelativeToRoot())->toStartWith('posts/')
        ->and($userMedia->getPathRelativeToRoot())->not->toStartWith('posts/')
        ->and($postMedia->id)->not->toBe($userMedia->id);
});

<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\Support\MediaLibrary\MediaLibraryCustomPathGenerator;
use App\User\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Build an in-memory Media item for the given model type and id.
 */
function mediaFor(string $modelType, int $id): Media
{
    $media = new Media;
    $media->id = $id;
    $media->model_type = $modelType;

    return $media;
}

it('stores post media under the posts folder', function (): void {
    $path = (new MediaLibraryCustomPathGenerator)->getPath(mediaFor(Post::MORPH_NAME, 7));

    expect($path)->toBe('posts'.DIRECTORY_SEPARATOR.'7'.DIRECTORY_SEPARATOR);
});

it('stores media of any other model type under its media id', function (string $modelType): void {
    $path = (new MediaLibraryCustomPathGenerator)->getPath(mediaFor($modelType, 8));

    expect($path)->toBe('8'.DIRECTORY_SEPARATOR);
})->with([
    'user' => User::MORPH_NAME,
    'comment' => Comment::MORPH_NAME,
]);

it('falls back to the media id for an unknown or empty model type', function (string $modelType): void {
    $path = (new MediaLibraryCustomPathGenerator)->getPath(mediaFor($modelType, 9));

    expect($path)->toBe('9'.DIRECTORY_SEPARATOR);
})->with(['unknown' => 'something-else', 'empty' => '']);

it('places the conversions in a dedicated subfolder of the base path', function (): void {
    $generator = new MediaLibraryCustomPathGenerator;

    expect($generator->getPathForConversions(mediaFor(Post::MORPH_NAME, 7)))
        ->toBe('posts'.DIRECTORY_SEPARATOR.'7'.DIRECTORY_SEPARATOR.'conversions'.DIRECTORY_SEPARATOR)
        ->and($generator->getPathForConversions(mediaFor(User::MORPH_NAME, 8)))
        ->toBe('8'.DIRECTORY_SEPARATOR.'conversions'.DIRECTORY_SEPARATOR);
});

it('places the responsive images in a dedicated subfolder of the base path', function (): void {
    $generator = new MediaLibraryCustomPathGenerator;

    expect($generator->getPathForResponsiveImages(mediaFor(Post::MORPH_NAME, 7)))
        ->toBe('posts'.DIRECTORY_SEPARATOR.'7'.DIRECTORY_SEPARATOR.'responsive-images'.DIRECTORY_SEPARATOR)
        ->and($generator->getPathForResponsiveImages(mediaFor(User::MORPH_NAME, 8)))
        ->toBe('8'.DIRECTORY_SEPARATOR.'responsive-images'.DIRECTORY_SEPARATOR);
});

it('keeps different media of the same post in different folders', function (): void {
    $generator = new MediaLibraryCustomPathGenerator;

    expect($generator->getPath(mediaFor(Post::MORPH_NAME, 1)))
        ->not->toBe($generator->getPath(mediaFor(Post::MORPH_NAME, 2)));
});

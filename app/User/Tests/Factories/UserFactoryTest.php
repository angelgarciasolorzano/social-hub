<?php

declare(strict_types=1);

use App\User\Database\Factories\UserFactory;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Run the callback with app_path() redirected to a temporary directory holding one
 * fake profile picture and one fake cover image where the seeding globs look for them.
 * The real seeding images are not versioned, so the test cannot rely on them.
 *
 * @param  Closure(): void  $callback
 */
function withTemporarySeedingImages(Application $application, Closure $callback): void
{
    $originalAppPath = $application->path();
    $root = sys_get_temp_dir().'/user-factory-images-'.uniqid('', true);
    $file = UploadedFile::fake()->image('seed.jpg');

    foreach ([User::TEST_PROFILE_IMAGES_GLOB_PATH, User::TEST_COVER_IMAGES_GLOB_PATH] as $globPath) {
        $directory = $root.'/'.dirname($globPath);

        File::makeDirectory($directory, 0755, true);
        File::put($directory.'/seed.jpg', $file->getContent());
    }

    $application->useAppPath($root);

    try {
        $callback();
    } finally {
        $application->useAppPath($originalAppPath);
        File::deleteDirectory($root);
    }
}

it('creates users without media by default', function (): void {
    foreach (range(1, 10) as $attempt) {
        $user = createUser();

        expect($user->getMedia(UserImageType::PROFILE_PICTURE->value()))->toBeEmpty()
            ->and($user->getMedia(UserImageType::COVER_IMAGE->value()))->toBeEmpty();
    }
});

it('attaches a seeding profile picture and cover image with the withImages state', function (): void {
    Storage::fake('public');

    withTemporarySeedingImages($this->app, function (): void {
        $user = UserFactory::new()->withImages()->createOne();

        expect($user->getMedia(UserImageType::PROFILE_PICTURE->value()))->toHaveCount(1)
            ->and($user->getMedia(UserImageType::COVER_IMAGE->value()))->toHaveCount(1);
    });
});

<?php

declare(strict_types=1);

use App\User\Database\Factories\UserFactory;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Support\Facades\Storage;

it('creates users without media by default', function (): void {
    foreach (range(1, 10) as $attempt) {
        $user = createUser();

        expect($user->getMedia(UserImageType::PROFILE_PICTURE->value()))->toBeEmpty()
            ->and($user->getMedia(UserImageType::COVER_IMAGE->value()))->toBeEmpty();
    }
});

it('attaches a seeding profile picture and cover image with the withImages state', function (): void {
    Storage::fake('public');

    withTemporarySeedingImages([User::TEST_PROFILE_IMAGES_GLOB_PATH, User::TEST_COVER_IMAGES_GLOB_PATH], function (): void {
        $user = UserFactory::new()->withImages()->createOne();

        expect($user->getMedia(UserImageType::PROFILE_PICTURE->value()))->toHaveCount(1)
            ->and($user->getMedia(UserImageType::COVER_IMAGE->value()))->toHaveCount(1);
    });
});

<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Models\Announcement;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('is registered in the morph map under the announcement key', function (): void {
    expect(Announcement::MORPH_NAME)->toBe('announcement')
        ->and(Relation::getMorphedModel('announcement'))->toBe(Announcement::class)
        ->and((new Announcement)->getMorphClass())->toBe('announcement');
});

it('stores the morph alias as the owner of its image and keeps a single image', function (): void {
    Storage::fake('public');

    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();
    $announcement = $factory->createOne();

    $announcement->addMedia(UploadedFile::fake()->image('first.jpg'))
        ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);
    $media = $announcement->addMedia(UploadedFile::fake()->image('second.jpg'))
        ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);

    expect($media->model_type)->toBe('announcement')
        ->and($announcement->refresh()->getMedia(Announcement::IMAGE_MEDIA_COLLECTION))->toHaveCount(1)
        ->and($announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->file_name)->toBe('second.jpg');
});

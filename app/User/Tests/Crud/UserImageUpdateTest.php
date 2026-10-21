<?php

declare(strict_types=1);

use App\User\Enums\UserImageType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores the uploaded image in the collection of its type', function (UserImageType $userImageType): void {
    Storage::fake('public');
    $user = createUser();

    $this->actingAs($user)
        ->post(route('user.update.image', $userImageType->value), [
            $userImageType->value => UploadedFile::fake()->image('image.jpg'),
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('message', $userImageType->successMessage());

    expect($user->refresh()->getFirstMedia($userImageType->value))->not->toBeNull();
})->with(UserImageType::cases());

it('rejects a file that is not an image', function (): void {
    Storage::fake('public');
    $user = createUser();

    $this->actingAs($user)
        ->post(route('user.update.image', UserImageType::COVER_IMAGE->value), [
            UserImageType::COVER_IMAGE->value => UploadedFile::fake()->create('notes.txt'),
        ])
        ->assertSessionHasErrors(UserImageType::COVER_IMAGE->value);

    expect($user->refresh()->getFirstMedia(UserImageType::COVER_IMAGE->value))->toBeNull();
});

it('returns 404 for an unknown image type', function (): void {
    $this->actingAs(createUser())
        ->post('/user/image/banner', ['banner' => UploadedFile::fake()->image('image.jpg')])
        ->assertNotFound();
});

<?php

declare(strict_types=1);

use App\User\Enums\UserImageType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * The only activity recorded in the `account` log for the given event.
 */
function soleAccountActivity(string $eventName): Activity
{
    return Activity::query()
        ->where('log_name', 'account')
        ->where('event', $eventName)
        ->sole();
}

it('records the names of the changed account settings without their values', function (): void {
    $user = createUser();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'phone' => '+505 8888 9999',
        'preferredLocale' => $user->preferred_locale->value,
        'biography' => 'Biografía privada del usuario.',
    ])->assertSessionHasNoErrors();

    $activity = soleAccountActivity('account.updated');

    expect($activity)
        ->subject_id->toBe($user->id)
        ->causer_id->toBe($user->id)
        ->and($activity->properties?->get('changed_attributes'))->toEqualCanonicalizing(['phone', 'biography'])
        ->and($activity->withoutRelations()->toJson())->not->toContain('+505 8888 9999')
        ->not->toContain('Biografía privada del usuario.');
});

it('does not record saving the account settings without changes', function (): void {
    $user = createUser();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'preferredLocale' => $user->preferred_locale->value,
    ])->assertSessionHasNoErrors();

    expect(Activity::query()->where('log_name', 'account')->exists())->toBeFalse();
});

it('records a changed email from the profile form without the address', function (): void {
    $user = createUser();

    $this->actingAs($user)->put(route('user.update', $user), [
        'name' => $user->name,
        'email' => 'nuevo-correo@example.com',
    ])->assertSessionHasNoErrors();

    $activity = soleAccountActivity('account.updated');

    expect($activity->properties?->get('changed_attributes'))->toContain('email')
        ->and($activity->withoutRelations()->toJson())->not->toContain('nuevo-correo@example.com');
});

it('records a replaced profile picture with its image type', function (): void {
    Storage::fake('public');
    $user = createUser();

    $this->actingAs($user)->post(route('user.update.image', UserImageType::PROFILE_PICTURE->value), [
        UserImageType::PROFILE_PICTURE->value => UploadedFile::fake()->image('profile.jpg'),
    ])->assertSessionHasNoErrors();

    expect(soleAccountActivity('account.image_updated')->properties?->get('image_type'))->toBe('profile_picture');
});

it('does not record an image upload rejected by validation', function (): void {
    Storage::fake('public');
    $user = createUser();

    $this->actingAs($user)->post(route('user.update.image', UserImageType::PROFILE_PICTURE->value), [
        UserImageType::PROFILE_PICTURE->value => UploadedFile::fake()->create('notes.txt'),
    ])->assertSessionHasErrors();

    expect(Activity::query()->where('log_name', 'account')->exists())->toBeFalse();
});

it('keeps the id of a deleted account as subject and causer', function (): void {
    $user = createUser();

    $this->actingAs($user)->delete(route('user.destroy', $user), ['password' => 'password']);

    $this->assertModelMissing($user);

    expect(soleAccountActivity('account.deleted'))
        ->subject_id->toBe($user->id)
        ->causer_id->toBe($user->id)
        ->subject->toBeNull();
});

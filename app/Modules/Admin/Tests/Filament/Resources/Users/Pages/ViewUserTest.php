<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

/**
 * @return array{components: array<string, Entry>, html: string}
 */
function renderUserDetailAsAuthorizedAdmin(TestCase $testCase, User $targetUser): array
{
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $testCase->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(ViewUser::class, ['record' => $targetUser->id])
        ->assertOk();
    $detailPageComponent = $testable->instance();

    throw_unless($detailPageComponent instanceof ViewUser, LogicException::class, 'The detail test must resolve the Filament ViewUser page.');

    /** @var ViewUser $detailPageComponent */
    $infolist = $detailPageComponent->getSchema('infolist');

    throw_unless($infolist instanceof Schema, LogicException::class, 'The user detail page must expose its infolist schema.');

    /** @var array<string, Entry> $components */
    $components = array_filter(
        $infolist->getFlatComponents(withActions: false, withHidden: true),
        static fn (mixed $component): bool => $component instanceof Entry,
    );

    return [
        'components' => $components,
        'html' => $testable->html(),
    ];
}

it('limits the user detail to allowlisted fields', function (): void {
    $detail = renderUserDetailAsAuthorizedAdmin($this, createUser());

    expect(array_keys($detail['components']))->toBe([
        'profile_picture',
        'name',
        'email',
        'cover_image_status',
        'cover_image',
        'status',
        'email_verification_status',
        'email_verified_at',
        'created_at',
        'last_login_at',
    ]);
});

it('shows the user identity in matching detail fields', function (): void {
    $targetUser = createUser();
    $detail = renderUserDetailAsAuthorizedAdmin($this, $targetUser);
    $components = $detail['components'];

    expect($components['name']->getState())->toBe($targetUser->name)
        ->and($components['email']->getState())->toBe($targetUser->email);
});

it('shows account timestamps in matching detail fields', function (): void {
    $targetUser = createUser();
    $detail = renderUserDetailAsAuthorizedAdmin($this, $targetUser);
    $components = $detail['components'];

    expect($components['email_verified_at']->getState())->toEqual($targetUser->email_verified_at)
        ->and($components['created_at']->getState())->toEqual($targetUser->created_at)
        ->and($components['last_login_at']->getState())->toBe($targetUser->last_login_at)
        ->and($detail['html'])->toContain('Email verification and sign-in history for this user.');
});

it('uses a compact profile fallback and explains when no cover image has been added', function (): void {
    Storage::fake((string) config('media-library.disk_name'));

    $targetUser = createUser();
    $targetUser->clearMediaCollection(UserImageType::PROFILE_PICTURE->value());
    $targetUser->clearMediaCollection(UserImageType::COVER_IMAGE->value());

    $detail = renderUserDetailAsAuthorizedAdmin($this, $targetUser);
    $components = $detail['components'];

    expect($components['profile_picture'])->toBeInstanceOf(ImageEntry::class)
        ->and($components['profile_picture']->getState())->toBe(url(User::DEFAULT_PROFILE_PICTURE_PATH))
        ->and($components['cover_image'])->toBeInstanceOf(ImageEntry::class)
        ->and($components['cover_image']->getState())->toBeNull()
        ->and($components['cover_image_status']->getState())->toBe('Not added')
        ->and($detail['html'])->not->toContain(url(User::DEFAULT_COVER_IMAGE_PATH));
});

it('does not expose two factor secrets on the user detail page', function (): void {
    Storage::fake((string) config('media-library.disk_name'));

    $targetUser = createUser();
    $totpSecretSentinel = 'totp-secret-sentinel-4f82';
    $recoveryCodeSentinel = 'recovery-code-sentinel-7b13';

    $targetUser->forceFill([
        'two_factor_secret' => Crypt::encrypt($totpSecretSentinel),
        'two_factor_recovery_codes' => Crypt::encrypt(json_encode(
            [$recoveryCodeSentinel],
            JSON_THROW_ON_ERROR,
        )),
    ])->save();

    $encryptedTotpSecret = $targetUser->getRawOriginal('two_factor_secret');
    $encryptedRecoveryCodes = $targetUser->getRawOriginal('two_factor_recovery_codes');
    $detail = renderUserDetailAsAuthorizedAdmin($this, $targetUser);

    expect($detail['html'])
        ->not->toContain($totpSecretSentinel)
        ->not->toContain($recoveryCodeSentinel)
        ->not->toContain($encryptedTotpSecret)
        ->not->toContain($encryptedRecoveryCodes)
        ->not->toContain('two_factor_secret')
        ->not->toContain('two_factor_recovery_codes');
});

it('shows profile and cover images from their respective media collections', function (): void {
    Storage::fake((string) config('media-library.disk_name'));

    $targetUser = createUser();
    $targetUser->addMedia(UploadedFile::fake()->image('profile-picture.jpg'))
        ->toMediaCollection(UserImageType::PROFILE_PICTURE->value());
    $targetUser->addMedia(UploadedFile::fake()->image('cover-image.jpg'))
        ->toMediaCollection(UserImageType::COVER_IMAGE->value());

    $detail = renderUserDetailAsAuthorizedAdmin($this, $targetUser);
    $components = $detail['components'];
    $profilePictureUrl = url($targetUser->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()));
    $coverImageUrl = url($targetUser->getFirstMediaUrl(UserImageType::COVER_IMAGE->value()));

    expect($components['profile_picture']->getState())->toBe($profilePictureUrl)
        ->and($components['cover_image']->getState())->toBe($coverImageUrl)
        ->and($detail['html'])->toContain($profilePictureUrl)
        ->toContain($coverImageUrl);
});

it('denies a user detail when the admin lacks users view permission', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    $authenticatedUser->givePermissionTo(AdminPermission::AdminUsersViewAny->value);

    $targetUser = createUser();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(ViewUser::class, ['record' => $targetUser->id])
        ->assertForbidden();
});

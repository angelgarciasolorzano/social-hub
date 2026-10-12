<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use LogicException;

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Filament::setCurrentPanel('admin');
});

it('allows admins with users view permission to open an allowlisted detail without exposing two factor secrets', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

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

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(ViewUser::class, ['record' => $targetUser->id])
        ->assertOk();

    $detailPageComponent = $testable->instance();

    throw_unless($detailPageComponent instanceof ViewUser, LogicException::class, 'The detail test must resolve the Filament ViewUser page.');

    $infolist = $detailPageComponent->getSchema('infolist');

    throw_unless($infolist instanceof Schema, LogicException::class, 'The user detail page must expose its infolist schema.');

    $infolistComponents = array_filter(
        $infolist->getFlatComponents(withActions: false, withHidden: true),
        static fn (mixed $component): bool => $component instanceof Component,
    );

    expect(array_keys($infolistComponents))->toBe([
        'name',
        'email',
        'email_verified_at',
        'created_at',
        'last_login_at',
    ])
        ->and($infolistComponents['name']->getState())->toBe($targetUser->name)
        ->and($infolistComponents['email']->getState())->toBe($targetUser->email)
        ->and($infolistComponents['email_verified_at']->getState())->toEqual($targetUser->email_verified_at)
        ->and($infolistComponents['created_at']->getState())->toEqual($targetUser->created_at)
        ->and($infolistComponents['last_login_at']->getState())->toBe($targetUser->last_login_at);

    $serializedResponse = $testable->html();

    expect($serializedResponse)
        ->not->toContain($totpSecretSentinel)
        ->not->toContain($recoveryCodeSentinel)
        ->not->toContain($encryptedTotpSecret)
        ->not->toContain($encryptedRecoveryCodes)
        ->not->toContain('two_factor_secret')
        ->not->toContain('two_factor_recovery_codes');
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

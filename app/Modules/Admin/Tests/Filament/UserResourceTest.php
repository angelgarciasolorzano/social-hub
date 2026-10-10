<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\User\Models\User;
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

it('allows admins with the users reader role to list and search users by name and email', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $user = createUser();
    $user->forceFill([
        'name' => 'Searchable Name Sentinel',
        'email' => 'name-match@example.test',
    ])->save();

    $emailMatch = createUser();
    $emailMatch->forceFill([
        'name' => 'Email Match User',
        'email' => 'searchable-email@example.test',
    ])->save();

    $nonMatch = createUser();
    $nonMatch->forceFill([
        'name' => 'Unrelated User',
        'email' => 'unrelated@example.test',
    ])->save();

    $this->actingAs($authenticatedUser, 'web');

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertSee($user->name)
        ->assertSee($emailMatch->email)
        ->assertSee($nonMatch->email);

    Livewire::test(ListUsers::class)
        ->set('tableSearch', 'Searchable Name Sentinel')
        ->assertSee($user->name)
        ->assertDontSee($emailMatch->email)
        ->assertDontSee($nonMatch->email);

    Livewire::test(ListUsers::class)
        ->set('tableSearch', 'searchable-email@example.test')
        ->assertSee($emailMatch->email)
        ->assertDontSee($user->name)
        ->assertDontSee($nonMatch->email);
});

it('allows admins with users view permission to open an allowlisted detail without exposing two factor secrets', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
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

    $this->actingAs($authenticatedUser, 'web');

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

it('denies the users list when the admin lacks users view any permission', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    $authenticatedUser->givePermissionTo(AdminPermission::UsersView->value);

    $this->actingAs($authenticatedUser, 'web');

    Livewire::test(ListUsers::class)
        ->assertForbidden();
});

it('denies a user detail when the admin lacks users view permission', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    $authenticatedUser->givePermissionTo(AdminPermission::UsersViewAny->value);

    $targetUser = createUser();

    $this->actingAs($authenticatedUser, 'web');

    Livewire::test(ViewUser::class, ['record' => $targetUser->id])
        ->assertForbidden();
});

it('rejects direct user create edit and delete requests without mutating users', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $targetUser = createUser();
    $originalName = $targetUser->name;
    $originalEmail = $targetUser->email;
    $originalUserCount = User::query()->count('id');

    $this->actingAs($authenticatedUser, 'web');

    $this->get('/admin/users/create')->assertNotFound();
    $this->get('/admin/users/'.$targetUser->id.'/edit')->assertNotFound();
    $this->post('/admin/users')
        ->assertMethodNotAllowed();
    $this->patch('/admin/users/'.$targetUser->id, [
        'name' => 'Unauthorized Name Change',
        'email' => 'unauthorized-change@example.test',
    ])
        ->assertMethodNotAllowed();
    $this->delete('/admin/users/'.$targetUser->id)
        ->assertMethodNotAllowed();

    expect(User::query()->count('id'))->toBe($originalUserCount)
        ->and($targetUser->refresh()->name)->toBe($originalName)
        ->and($targetUser->email)->toBe($originalEmail);
});

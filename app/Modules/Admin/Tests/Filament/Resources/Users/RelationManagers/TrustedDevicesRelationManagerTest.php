<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\TrustedDevicesRelationManager;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows only scoped trusted device details without exposing sensitive data', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();
    $otherUser = createUser();
    $currentTime = now()->toImmutable();
    $this->travelTo($currentTime);

    $tokenHashSentinel = 'admin-device-token-hash-sentinel';
    $userAgentSentinel = 'admin-device-user-agent-sentinel';
    $ipSentinel = 'admin-device-ip-sentinel';

    createTrustedDevice($targetUser, [
        'token_hash' => $tokenHashSentinel,
        'user_agent' => $userAgentSentinel,
        'browser' => 'Chromium',
        'browser_version' => '140.0',
        'os_name' => 'macOS',
        'os_version' => '15.1',
        'ip' => $ipSentinel,
        'last_used_at' => $currentTime->subHour(),
        'expires_at' => $currentTime->addDay(),
    ]);

    createTrustedDevice($otherUser, [
        'name' => 'Foreign user device sentinel',
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(TrustedDevicesRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertSeeText('Chromium 140.0 · macOS 15.1')
        ->assertSeeText('Active')
        ->assertDontSee('Foreign user device sentinel');

    expect($testable->html())
        ->not->toContain($tokenHashSentinel)
        ->not->toContain($userAgentSentinel)
        ->not->toContain($ipSentinel);
});

it('derives active expired and revoked states including the expiry boundary', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();
    $currentTime = now()->toImmutable();

    $this->travelTo($currentTime);

    $trustedDevice = createTrustedDevice($targetUser, [
        'expires_at' => $currentTime->addDay(),
    ]);

    $expiredDevice = createTrustedDevice($targetUser, [
        'expires_at' => $currentTime->subSecond(),
    ]);

    $boundaryDevice = createTrustedDevice($targetUser, [
        'expires_at' => $currentTime,
    ]);

    $revokedDevice = createTrustedDevice($targetUser, [
        'expires_at' => $currentTime->subDay(),
    ]);

    $revokedDevice->delete();

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(TrustedDevicesRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertSeeText('Active')
        ->assertSeeText('Expired')
        ->assertSeeText('Revoked');

    $relationManagerComponent = $testable->instance();

    throw_unless($relationManagerComponent instanceof HasTable, LogicException::class, 'The trusted devices component must expose a Filament table.');

    /** @var HasTable&Component $relationManagerComponent */
    $statusColumn = $relationManagerComponent->getTable()->getColumn('status');

    throw_unless($statusColumn instanceof TextColumn, LogicException::class, 'The trusted devices table must expose its status column.');

    /** @var TextColumn $statusColumn */
    expect($statusColumn->record($trustedDevice)->getState())
        ->toBe('Active')
        ->and($statusColumn->record($expiredDevice)->getState())
        ->toBe('Expired')
        ->and($statusColumn->record($boundaryDevice)->getState())
        ->toBe('Expired')
        ->and($statusColumn->record($revokedDevice)->getState())
        ->toBe('Revoked');
});

it('registers the trusted device relation on the user resource', function (): void {
    expect(UserResource::getRelations())
        ->toContain(TrustedDevicesRelationManager::class);
});

it('defines trusted device columns without mutation actions', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();
    createTrustedDevice($targetUser);

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(TrustedDevicesRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])->assertOk();

    $relationManagerComponent = $testable->instance();

    throw_unless($relationManagerComponent instanceof HasTable, LogicException::class, 'The trusted devices component must expose a Filament table.');

    /** @var HasTable&Component $relationManagerComponent */
    $table = $relationManagerComponent->getTable();

    expect(array_keys($table->getColumns()))
        ->toBe(['name', 'device_summary', 'last_used_at', 'expires_at', 'status'])
        ->and($table->getRecordActions())
        ->toBeEmpty()
        ->and($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});

it('uses ten rows as the trusted device pagination default', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();

    $this->actingAs($authenticatedUser, 'admin');

    $component = Livewire::test(TrustedDevicesRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The trusted devices component must expose a Filament table.');

    /** @var HasTable&Component $component */
    $table = $component->getTable();

    expect($table->isPaginated())->toBeTrue()
        ->and($table->getDefaultPaginationPageOption())
        ->toBe(10);
});

it('paginates only the selected users trusted devices', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();

    foreach (range(1, 11) as $sequence) {
        createTrustedDevice($targetUser, [
            'name' => 'Device '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(TrustedDevicesRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertSee('Device 11')
        ->assertDontSee('Device 01')
        ->call('setPage', 2)
        ->assertSee('Device 01')
        ->assertDontSee('Device 02');
});

it('forbids admins without users view permission from viewing the relation', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    $authenticatedUser->givePermissionTo(AdminPermission::AdminUsersViewAny->value);

    $targetUser = createUser();

    $this->actingAs($authenticatedUser, 'admin');

    assertTrustedDevicesRelationIsForbidden($targetUser);
});

function assertTrustedDevicesRelationIsForbidden(User $targetUser): void
{
    Livewire::test(TrustedDevicesRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertForbidden();
}

<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\TrustedDevicesRelationManager;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

/**
 * @return Testable<Component>
 */
function trustedDevicesRelationManagerFor(User $user): Testable
{
    return Livewire::test(TrustedDevicesRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ]);
}

it('shows only scoped trusted device details without exposing sensitive data', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

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

    $testable = trustedDevicesRelationManagerFor($targetUser)
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
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

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

    $testable = trustedDevicesRelationManagerFor($targetUser)
        ->assertOk()
        ->assertSeeText('Active')
        ->assertSeeText('Expired')
        ->assertSeeText('Revoked');

    $relationManagerComponent = FilamentTableRecords::table($testable);
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
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $targetUser = createUser();
    createTrustedDevice($targetUser);

    $testable = trustedDevicesRelationManagerFor($targetUser)->assertOk();

    $relationManagerComponent = FilamentTableRecords::table($testable);
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
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $targetUser = createUser();

    $component = trustedDevicesRelationManagerFor($targetUser)
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
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $targetUser = createUser();

    foreach (range(1, 11) as $sequence) {
        createTrustedDevice($targetUser, [
            'name' => 'Device '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]);
    }

    trustedDevicesRelationManagerFor($targetUser)
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
    trustedDevicesRelationManagerFor($targetUser)
        ->assertForbidden();
}

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
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

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

function assertTrustedDevicesRelationIsForbidden(User $targetUser): void
{
    trustedDevicesRelationManagerFor($targetUser)
        ->assertForbidden();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('loads trusted devices lazily and displays a loading skeleton', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $table = FilamentTableRecords::table(trustedDevicesRelationManagerFor(createUser()))->getTable();

    expect(TrustedDevicesRelationManager::isLazy())->toBeTrue()
        ->and($table->isLoadingDeferred())->toBeFalse()
        ->and($table->hasLoadingSkeleton())->toBeTrue();
});

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
        ->toBe('active')
        ->and($statusColumn->record($expiredDevice)->getState())
        ->toBe('expired')
        ->and($statusColumn->record($boundaryDevice)->getState())
        ->toBe('expired')
        ->and($statusColumn->record($revokedDevice)->getState())
        ->toBe('revoked')
        ->and($statusColumn->getColor('revoked', $revokedDevice))
        ->toBe('danger');
});

it('shows the trusted devices tab and device states in Spanish', function (): void {
    app()->setLocale('es');

    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $targetUser = createUser();
    createTrustedDevice($targetUser, ['expires_at' => now()->addDay()]);
    createTrustedDevice($targetUser, ['expires_at' => now()->subDay(), 'last_used_at' => null]);
    createTrustedDevice($targetUser, ['expires_at' => now()->subDay()])->delete();

    $testable = trustedDevicesRelationManagerFor($targetUser)
        ->assertOk()
        ->assertSee('Dispositivo')
        ->assertSee('Navegador / Sistema')
        ->assertSee('Último uso')
        ->assertSee('Vence')
        ->assertSee('Estado')
        ->assertSeeText('Activo')
        ->assertSeeText('Vencido')
        ->assertSeeText('Revocado')
        ->assertSeeText('Nunca')
        ->assertDontSeeText('Expired');

    $statusColumn = FilamentTableRecords::table($testable)->getTable()->getColumn('status');

    throw_unless($statusColumn instanceof TextColumn, LogicException::class, 'The trusted devices table must expose its status column.');

    expect($statusColumn->getColor('expired'))->toBe('warning')
        ->and(TrustedDevicesRelationManager::getTitle($targetUser, ViewUser::class))->toBe('Dispositivos de confianza');
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

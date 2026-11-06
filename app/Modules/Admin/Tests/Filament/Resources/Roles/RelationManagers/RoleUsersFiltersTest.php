<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleUsersRelationManager;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The users tab of a role, signed in as an administrator who may read roles and users.
 *
 * @return Testable<Component>
 */
function filterableRoleUsersTab(TestCase $testCase, Role $role): Testable
{
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    return Livewire::test(RoleUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class]);
}

/**
 * A new user holding the role.
 */
function roleHolder(Role $role): User
{
    $user = createUser();
    $user->assignRole($role);

    return $user;
}

/**
 * Record that the role was given to the user at the given time.
 */
function recordRoleHolderGrant(Role $role, User $user, string $grantedAt): void
{
    ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->performedOn($user)->causedBy(createUser())
        ->createOne(['properties' => ['role' => $role->name], 'created_at' => CarbonImmutable::parse($grantedAt)]);
}

/**
 * The keys of the users the tab shows.
 *
 * @param  Testable<Component>  $testable
 * @return list<string>
 */
function filteredRoleHolderKeys(Testable $testable): array
{
    return FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), User::class);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('filters the holders by account status', function (): void {
    $role = RoleFactory::new()->createOne();
    $activeUser = roleHolder($role);
    $suspendedUser = roleHolder($role);
    $suspendedUser->forceFill(['status' => AccountStatus::Suspended])->save();

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['status', AccountStatus::Suspended->value]);

    expect(filteredRoleHolderKeys($testable))->toBe([FilamentTableRecords::recordKey($suspendedUser)])
        ->and(filteredRoleHolderKeys($testable))->not->toContain(FilamentTableRecords::recordKey($activeUser));
});

it('filters the holders by panel MFA', function (bool $hasPanelMfa): void {
    $role = RoleFactory::new()->createOne();
    $userWithMfa = roleHolder($role);
    $userWithMfa->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();
    $userWithoutMfa = roleHolder($role);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['panel_mfa', $hasPanelMfa]);

    expect(filteredRoleHolderKeys($testable))->toBe([FilamentTableRecords::recordKey($hasPanelMfa ? $userWithMfa : $userWithoutMfa)]);
})->with([true, false]);

it('filters the holders who also hold another role', function (): void {
    $role = RoleFactory::new()->createOne();
    $otherRole = RoleFactory::new()->createOne();
    $userWithBoth = roleHolder($role);
    $userWithBoth->assignRole($otherRole);
    roleHolder($role);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['also_has_role', [$otherRole->name]]);

    expect(filteredRoleHolderKeys($testable))->toBe([FilamentTableRecords::recordKey($userWithBoth)]);
});

it('does not offer this role among the other roles', function (): void {
    $role = RoleFactory::new()->createOne();
    $otherRole = RoleFactory::new()->createOne();

    /** @var TestCase $testCase */
    $testCase = $this;
    $filter = FilamentTableRecords::table(filterableRoleUsersTab($testCase, $role))->getTable()->getFilter('also_has_role');

    expect($filter)->toBeInstanceOf(SelectFilter::class);

    /** @var SelectFilter $filter */
    $options = $filter->getOptions();

    expect($options)->toHaveKey($otherRole->name)
        ->and($options)->not->toHaveKey($role->name);
});

it('filters the holders by the date of their latest grant', function (): void {
    $role = RoleFactory::new()->createOne();
    $user = roleHolder($role);
    recordRoleHolderGrant($role, $user, '2026-10-10 12:00:00');
    $grantedAgainLater = roleHolder($role);
    recordRoleHolderGrant($role, $grantedAgainLater, '2026-10-12 12:00:00');
    recordRoleHolderGrant($role, $grantedAgainLater, '2026-11-02 12:00:00');
    roleHolder($role);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['assigned_between', ['assigned_from' => '2026-10-01', 'assigned_until' => '2026-10-31']]);

    expect(filteredRoleHolderKeys($testable))->toBe([FilamentTableRecords::recordKey($user)]);
});

it('filters the holders who did not sign in recently, including those who never did', function (): void {
    $role = RoleFactory::new()->createOne();
    $recentUser = roleHolder($role);
    $recentUser->forceFill(['last_login_at' => now()->subDays(10)])->save();
    $staleUser = roleHolder($role);
    $staleUser->forceFill(['last_login_at' => now()->subDays(120)])->save();
    $user = roleHolder($role);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['inactive_for', '90']);

    expect(filteredRoleHolderKeys($testable))->toEqualCanonicalizing([
        FilamentTableRecords::recordKey($staleUser),
        FilamentTableRecords::recordKey($user),
    ]);
});

it('applies several filters together', function (): void {
    $role = RoleFactory::new()->createOne();
    $otherRole = RoleFactory::new()->createOne();
    $matchingUser = roleHolder($role);
    $matchingUser->assignRole($otherRole);
    recordRoleHolderGrant($role, $matchingUser, '2026-10-10 12:00:00');
    $userWithMfa = roleHolder($role);
    $userWithMfa->assignRole($otherRole);
    $userWithMfa->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();
    recordRoleHolderGrant($role, $userWithMfa, '2026-10-10 12:00:00');
    $userGrantedEarlier = roleHolder($role);
    $userGrantedEarlier->assignRole($otherRole);
    recordRoleHolderGrant($role, $userGrantedEarlier, '2026-09-10 12:00:00');

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['also_has_role', [$otherRole->name]]);
    $testable->__call('filterTable', ['panel_mfa', false]);
    $testable->__call('filterTable', ['assigned_between', ['assigned_from' => '2026-10-01', 'assigned_until' => '2026-10-31']]);

    expect(filteredRoleHolderKeys($testable))->toBe([FilamentTableRecords::recordKey($matchingUser)]);
});

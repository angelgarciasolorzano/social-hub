<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleUsersRelationManager;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Admin\Tests\Support\RoleActivity;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
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
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminUsersReader);

    return Livewire::test(RoleUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class]);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('filters the holders by account status', function (): void {
    $role = RoleFactory::new()->createOne();
    $activeUser = createUserHoldingRoles($role);
    $suspendedUser = createUserHoldingRoles($role);
    $suspendedUser->forceFill(['status' => AccountStatus::Suspended])->save();

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['status', AccountStatus::Suspended->value]);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toBe([FilamentTableRecords::recordKey($suspendedUser)])
        ->and(FilamentTableRecords::shownKeys($testable, User::class))->not->toContain(FilamentTableRecords::recordKey($activeUser));
});

it('filters the holders by panel MFA', function (bool $hasPanelMfa): void {
    $role = RoleFactory::new()->createOne();
    $userWithMfa = createUserHoldingRoles($role);
    $userWithMfa->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();
    $userWithoutMfa = createUserHoldingRoles($role);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['panel_mfa', $hasPanelMfa]);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toBe([FilamentTableRecords::recordKey($hasPanelMfa ? $userWithMfa : $userWithoutMfa)]);
})->with([true, false]);

it('filters the holders who also hold another role', function (): void {
    $role = RoleFactory::new()->createOne();
    $otherRole = RoleFactory::new()->createOne();
    $userWithBoth = createUserHoldingRoles($role, $otherRole);
    createUserHoldingRoles($role);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['also_has_role', [$otherRole->name]]);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toBe([FilamentTableRecords::recordKey($userWithBoth)]);
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
    $user = createUserHoldingRoles($role);
    RoleActivity::grant($role, $user, at: '2026-10-10 12:00:00');
    $grantedAgainLater = createUserHoldingRoles($role);
    RoleActivity::grant($role, $grantedAgainLater, at: '2026-10-12 12:00:00');
    RoleActivity::grant($role, $grantedAgainLater, at: '2026-11-02 12:00:00');
    createUserHoldingRoles($role);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['assigned_between', ['assigned_from' => '2026-10-01', 'assigned_until' => '2026-10-31']]);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toBe([FilamentTableRecords::recordKey($user)]);
});

it('filters the holders who did not sign in recently, including those who never did', function (): void {
    $role = RoleFactory::new()->createOne();
    $recentUser = createUserHoldingRoles($role);
    $recentUser->forceFill(['last_login_at' => now()->subDays(10)])->save();
    $staleUser = createUserHoldingRoles($role);
    $staleUser->forceFill(['last_login_at' => now()->subDays(120)])->save();
    $user = createUserHoldingRoles($role);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['inactive_for', '90']);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toEqualCanonicalizing([
        FilamentTableRecords::recordKey($staleUser),
        FilamentTableRecords::recordKey($user),
    ]);
});

it('applies several filters together', function (): void {
    $role = RoleFactory::new()->createOne();
    $otherRole = RoleFactory::new()->createOne();
    $matchingUser = createUserHoldingRoles($role, $otherRole);
    RoleActivity::grant($role, $matchingUser, at: '2026-10-10 12:00:00');
    $userWithMfa = createUserHoldingRoles($role, $otherRole);
    $userWithMfa->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();
    RoleActivity::grant($role, $userWithMfa, at: '2026-10-10 12:00:00');
    $userGrantedEarlier = createUserHoldingRoles($role, $otherRole);
    RoleActivity::grant($role, $userGrantedEarlier, at: '2026-09-10 12:00:00');

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableRoleUsersTab($testCase, $role);
    $testable->__call('filterTable', ['also_has_role', [$otherRole->name]]);
    $testable->__call('filterTable', ['panel_mfa', false]);
    $testable->__call('filterTable', ['assigned_between', ['assigned_from' => '2026-10-01', 'assigned_until' => '2026-10-31']]);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toBe([FilamentTableRecords::recordKey($matchingUser)]);
});

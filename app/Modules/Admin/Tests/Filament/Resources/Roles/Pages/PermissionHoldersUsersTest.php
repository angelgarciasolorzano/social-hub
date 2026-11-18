<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Admin\Tests\Support\QueryLog;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Tests\TestCase;

/**
 * The keys of the given users, sorted as the table sorts them by name.
 *
 * @return list<string>
 */
function holderKeysByName(User ...$users): array
{
    usort($users, static fn (User $first, User $second): int => strcmp($first->name, $second->name));

    return array_map(FilamentTableRecords::recordKey(...), $users);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminUsersReader);
});

it('lists exactly the users who hold the permission through any role, once each', function (): void {
    $role = adminCatalogRole(AdminRole::AdminBackupsOperator);
    $supportRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte']);
    $postsReader = adminCatalogRole(AdminRole::AdminPostsReader);
    $user = createUserHoldingRoles($role, $supportRole, $postsReader);
    $holderThroughCustomRole = createUserHoldingRoles($supportRole);
    $nonHolder = createUserHoldingRoles($postsReader);

    $shownKeys = FilamentTableRecords::shownKeys(permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value), User::class);

    expect($shownKeys)->toBe(holderKeysByName($user, $holderThroughCustomRole))
        ->and($shownKeys)->not->toContain(FilamentTableRecords::recordKey($nonHolder));
});

it('shows through which roles each user holds the permission, leaving out roles that do not grant it', function (): void {
    $supportRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte']);
    $user = createUserHoldingRoles($supportRole, adminCatalogRole(AdminRole::AdminBackupsOperator), adminCatalogRole(AdminRole::AdminPostsReader));

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->__call('assertTableColumnStateSet', ['via_roles', [AdminRole::AdminBackupsOperator->label(), 'Soporte'], $user]);
});

it('shows the holders of another permission when it is picked on the page', function (): void {
    $supportRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte']);
    $role = adminCatalogRole(AdminRole::AdminPostsReader);
    $user = createUserHoldingRoles($supportRole);
    $postsHolder = createUserHoldingRoles($role);

    $testable = permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->set('permission', AdminPermission::AdminPostsView->value);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toContain(FilamentTableRecords::recordKey($postsHolder))
        ->not->toContain(FilamentTableRecords::recordKey($user));
});

it('filters the holders by account status', function (): void {
    $supportRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte']);
    $user = createUserHoldingRoles($supportRole);
    $suspendedHolder = createSuspendedUser();
    $suspendedHolder->assignRole($supportRole);

    $testable = permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value);
    $testable->__call('filterTable', ['status', AccountStatus::Suspended->value]);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toBe([FilamentTableRecords::recordKey($suspendedHolder)])
        ->not->toContain(FilamentTableRecords::recordKey($user));
});

it('filters the holders by panel MFA', function (bool $hasPanelMfa): void {
    $supportRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte']);
    $user = createUserHoldingRoles($supportRole);
    $user->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();
    $holderWithoutMfa = createUserHoldingRoles($supportRole);

    $testable = permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value);
    $testable->__call('filterTable', ['panel_mfa', $hasPanelMfa]);

    expect(FilamentTableRecords::shownKeys($testable, User::class))->toBe([FilamentTableRecords::recordKey($hasPanelMfa ? $user : $holderWithoutMfa)]);
})->with([
    'with panel MFA' => [true],
    'without panel MFA' => [false],
]);

it('links each holder to their detail', function (): void {
    $user = createUserHoldingRoles(RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte']));

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->assertSee(UserResource::getUrl('view', ['record' => $user]), false);
});

it('runs the same number of queries whatever the number of roles and holders', function (): void {
    createUserHoldingRoles(adminCatalogRole(AdminRole::AdminBackupsOperator));

    $renderPage = static fn (): Testable => permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)->assertOk();

    QueryLog::count($renderPage);
    $queriesBefore = QueryLog::count($renderPage);

    $grantingRoles = [
        adminCatalogRole(AdminRole::AdminBackupsOperator),
        RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte']),
        RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Auditoría']),
        RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Guardia']),
    ];

    foreach (range(1, 12) as $index) {
        createUserHoldingRoles($grantingRoles[$index % 4], $grantingRoles[($index + 1) % 4]);
    }

    QueryLog::count($renderPage);

    expect(QueryLog::count($renderPage))->toBe($queriesBefore);
});

<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Tests\TestCase;

/**
 * A custom role that grants downloading backups, with the permission it needs.
 */
function createBackupsDownloadRole(string $label): Role
{
    $role = RoleFactory::new()->createOne(['label' => $label]);
    $role->givePermissionTo(AdminPermission::AdminBackupsView->value, AdminPermission::AdminBackupsDownload->value);

    return $role;
}

/**
 * The keys of the users the holders table shows, in its order.
 *
 * @param  Testable<Component>  $testable
 * @return list<string>
 */
function shownHolderKeys(Testable $testable): array
{
    return FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), User::class);
}

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

/**
 * Count the queries that rendering the page on the holders of downloading backups runs. Call it once
 * first, so Spatie's permission cache is warm and the measurements compare the page only.
 */
function countPermissionHoldersQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)->assertOk();

    $queryCount = count(DB::connection()->getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
});

it('lists exactly the users who hold the permission through any role, once each', function (): void {
    $role = adminCatalogRole(AdminRole::AdminBackupsOperator);
    $supportRole = createBackupsDownloadRole('Soporte');
    $postsReader = adminCatalogRole(AdminRole::AdminPostsReader);
    $user = createUserHoldingRoles($role, $supportRole, $postsReader);
    $holderThroughCustomRole = createUserHoldingRoles($supportRole);
    $nonHolder = createUserHoldingRoles($postsReader);

    $shownKeys = shownHolderKeys(permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value));

    expect($shownKeys)->toBe(holderKeysByName($user, $holderThroughCustomRole))
        ->and($shownKeys)->not->toContain(FilamentTableRecords::recordKey($nonHolder));
});

it('shows through which roles each user holds the permission, leaving out roles that do not grant it', function (): void {
    $supportRole = createBackupsDownloadRole('Soporte');
    $user = createUserHoldingRoles($supportRole, adminCatalogRole(AdminRole::AdminBackupsOperator), adminCatalogRole(AdminRole::AdminPostsReader));

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->__call('assertTableColumnStateSet', ['via_roles', [AdminRole::AdminBackupsOperator->label(), 'Soporte'], $user]);
});

it('shows the holders of another permission when it is picked on the page', function (): void {
    $supportRole = createBackupsDownloadRole('Soporte');
    $role = adminCatalogRole(AdminRole::AdminPostsReader);
    $user = createUserHoldingRoles($supportRole);
    $postsHolder = createUserHoldingRoles($role);

    $testable = permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->set('permission', AdminPermission::AdminPostsView->value);

    expect(shownHolderKeys($testable))->toContain(FilamentTableRecords::recordKey($postsHolder))
        ->not->toContain(FilamentTableRecords::recordKey($user));
});

it('filters the holders by account status', function (): void {
    $supportRole = createBackupsDownloadRole('Soporte');
    $user = createUserHoldingRoles($supportRole);
    $suspendedHolder = createSuspendedUser();
    $suspendedHolder->assignRole($supportRole);

    $testable = permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value);
    $testable->__call('filterTable', ['status', AccountStatus::Suspended->value]);

    expect(shownHolderKeys($testable))->toBe([FilamentTableRecords::recordKey($suspendedHolder)])
        ->not->toContain(FilamentTableRecords::recordKey($user));
});

it('filters the holders by panel MFA', function (bool $hasPanelMfa): void {
    $supportRole = createBackupsDownloadRole('Soporte');
    $user = createUserHoldingRoles($supportRole);
    $user->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();
    $holderWithoutMfa = createUserHoldingRoles($supportRole);

    $testable = permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value);
    $testable->__call('filterTable', ['panel_mfa', $hasPanelMfa]);

    expect(shownHolderKeys($testable))->toBe([FilamentTableRecords::recordKey($hasPanelMfa ? $user : $holderWithoutMfa)]);
})->with([
    'with panel MFA' => [true],
    'without panel MFA' => [false],
]);

it('links each holder to their detail', function (): void {
    $user = createUserHoldingRoles(createBackupsDownloadRole('Soporte'));

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->assertSee(UserResource::getUrl('view', ['record' => $user]), false);
});

it('runs the same number of queries whatever the number of roles and holders', function (): void {
    createUserHoldingRoles(adminCatalogRole(AdminRole::AdminBackupsOperator));

    countPermissionHoldersQueries();
    $queriesBefore = countPermissionHoldersQueries();

    $grantingRoles = [
        adminCatalogRole(AdminRole::AdminBackupsOperator),
        createBackupsDownloadRole('Soporte'),
        createBackupsDownloadRole('Auditoría'),
        createBackupsDownloadRole('Guardia'),
    ];

    foreach (range(1, 12) as $index) {
        createUserHoldingRoles($grantingRoles[$index % 4], $grantingRoles[($index + 1) % 4]);
    }

    countPermissionHoldersQueries();

    expect(countPermissionHoldersQueries())->toBe($queriesBefore);
});

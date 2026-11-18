<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Admin\Tests\Support\RoleActivity;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The roles list, signed in as a roles reader, on the custom roles tab so the catalog does not get in
 * the way.
 *
 * @return Testable<ListRoles>
 */
function filterableCustomRolesList(TestCase $testCase): Testable
{
    AdminFilamentTestContext::actingAsRolesReader($testCase);

    return Livewire::test(ListRoles::class)
        ->set('activeTab', 'custom')
        ->call('loadTable')
        ->set('tableRecordsPerPage', 50);
}

/**
 * The names of the roles the list shows, sorted.
 *
 * @param  Testable<ListRoles>  $testable
 * @return list<string>
 */
function filteredRoleNames(Testable $testable): array
{
    $roleNames = array_map(
        static fn (Role $role): string => $role->name,
        FilamentTableRecords::visibleRecords(FilamentTableRecords::table($testable), Role::class),
    );
    sort($roleNames);

    return $roleNames;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('keeps only the roles that hold every chosen permission', function (): void {
    $roleWithBoth = RoleFactory::new()->withPermissions(AdminPermission::AdminPostsViewAny, AdminPermission::AdminBackupsView)->createOne();
    RoleFactory::new()->withPermissions(AdminPermission::AdminPostsViewAny)->createOne();
    RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView)->createOne();

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableCustomRolesList($testCase);
    $testable->__call('filterTable', ['includes_permission', [AdminPermission::AdminPostsViewAny->value, AdminPermission::AdminBackupsView->value]]);

    expect(filteredRoleNames($testable))->toBe([$roleWithBoth->name]);
});

it('keeps the roles that hold any permission of the chosen area', function (): void {
    $roleWithOneBackupPermission = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView)->createOne();
    RoleFactory::new()->withPermissions(AdminPermission::AdminPostsViewAny)->createOne();

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableCustomRolesList($testCase);
    $testable->__call('filterTable', ['covers_area', 'backups']);

    expect(filteredRoleNames($testable))->toBe([$roleWithOneBackupPermission->name]);
});

it('filters the roles by sensitive permissions', function (bool $hasSensitive): void {
    $sensitiveRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne();
    $plainRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView)->createOne();

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableCustomRolesList($testCase);
    $testable->__call('filterTable', ['sensitive', $hasSensitive]);

    expect(filteredRoleNames($testable))->toBe([($hasSensitive ? $sensitiveRole : $plainRole)->name]);
})->with([true, false]);

it('filters the roles by whether they have users', function (bool $hasUsers): void {
    $roleWithUsers = RoleFactory::new()->createOne();
    $roleWithoutUsers = RoleFactory::new()->createOne();
    createUser()->assignRole($roleWithUsers);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableCustomRolesList($testCase);
    $testable->__call('filterTable', ['with_users', $hasUsers]);

    expect(filteredRoleNames($testable))->toBe([($hasUsers ? $roleWithUsers : $roleWithoutUsers)->name]);
})->with([true, false]);

it('keeps the roles last changed between the dates and leaves out roles without recorded changes', function (): void {
    $roleChangedInRange = RoleFactory::new()->createOne();
    $roleChangedBefore = RoleFactory::new()->createOne();
    RoleFactory::new()->createOne();

    RoleActivity::change($roleChangedInRange, ActivityLogAdminEvent::RoleUpdated, at: '2026-09-01 10:00:00');
    RoleActivity::change($roleChangedInRange, ActivityLogAdminEvent::RoleUpdated, at: '2026-10-10 10:00:00');
    RoleActivity::change($roleChangedBefore, ActivityLogAdminEvent::RoleUpdated, at: '2026-09-01 10:00:00');

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableCustomRolesList($testCase);
    $testable->__call('filterTable', ['last_changed_between', ['last_changed_from' => '2026-10-01', 'last_changed_until' => '2026-10-10']]);

    expect(filteredRoleNames($testable))->toBe([$roleChangedInRange->name]);
});

it('combines the filters with each other, with the active tab and with the search', function (): void {
    $matchingRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte de respaldos']);

    createUser()->assignRole($matchingRole);
    $roleWithoutUsers = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne(['label' => 'Soporte sin usuarios']);

    $otherLabelRole = RoleFactory::new()->withPermissions(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload)->createOne();
    createUser()->assignRole($otherLabelRole);

    /** @var TestCase $testCase */
    $testCase = $this;
    $testable = filterableCustomRolesList($testCase);
    $testable->__call('filterTable', ['covers_area', 'backups']);
    $testable->__call('filterTable', ['sensitive', true]);
    $testable->__call('filterTable', ['with_users', true]);
    $testable->__call('searchTable', ['Soporte']);

    expect(filteredRoleNames($testable))->toBe([$matchingRole->name]);
});

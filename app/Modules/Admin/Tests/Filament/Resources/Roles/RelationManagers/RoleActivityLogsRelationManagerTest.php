<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleActivityLogsRelationManager;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Enums\PreferredLocale;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

function roleWithHistory(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', 'admin')
        ->sole();
}

/**
 * @return Testable<Component>
 */
function roleActivityLogsRelationManagerFor(Role $role): Testable
{
    return Livewire::test(RoleActivityLogsRelationManager::class, [
        'ownerRecord' => $role,
        'pageClass' => ViewRole::class,
    ]);
}

function recordRolePermissionChange(Role $role, ActivityLogAdminEvent $activityLogAdminEvent): ActivityLog
{
    /** @var ActivityLogFactory $factory */
    $factory = ActivityLog::factory();

    return $factory->forEvent($activityLogAdminEvent)->performedOn($role)->createOne();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lists the permission changes of the role and not those of another role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminActivityReader);
    $role = roleWithHistory(AdminRole::AdminPostsReader);
    $activityLog = recordRolePermissionChange($role, ActivityLogAdminEvent::RolePermissionGranted);
    $revocation = recordRolePermissionChange($role, ActivityLogAdminEvent::RolePermissionRevoked);
    recordRolePermissionChange(roleWithHistory(AdminRole::AdminUsersReader), ActivityLogAdminEvent::RolePermissionGranted);

    $testable = roleActivityLogsRelationManagerFor($role)->assertOk();

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), ActivityLog::class))
        ->toEqualCanonicalizing([
            FilamentTableRecords::recordKey($activityLog),
            FilamentTableRecords::recordKey($revocation),
        ]);
});

it('shows the history title in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminActivityReader);

    expect(RoleActivityLogsRelationManager::getTitle(roleWithHistory(AdminRole::AdminPostsReader), ViewRole::class))
        ->toBe(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.relations.history.title'));
});

it('hides the tab from an administrator who cannot view the activity log', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = roleWithHistory(AdminRole::AdminPostsReader);

    expect(RoleActivityLogsRelationManager::canViewForRecord($role, ViewRole::class))->toBeFalse();

    roleActivityLogsRelationManagerFor($role)->assertForbidden();
});

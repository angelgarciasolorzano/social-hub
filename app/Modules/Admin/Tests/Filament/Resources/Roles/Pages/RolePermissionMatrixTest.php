<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Admin\Filament\Resources\Roles\Pages\RolePermissionMatrix;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The permissions the matrix shows as granted to each role, keyed by the role's label.
 *
 * @return array<string, array<int, string>>
 */
function matrixPermissionsByRoleLabel(): array
{
    $component = Livewire::test(RolePermissionMatrix::class)->instance();

    throw_unless($component instanceof RolePermissionMatrix, LogicException::class, 'The permission matrix page must be mounted.');

    $permissionsByRoleLabel = [];

    foreach ($component->getMatrixRoles() as $matrixRole) {
        $permissionsByRoleLabel[$matrixRole['label']] = $matrixRole['permissions'];
    }

    return $permissionsByRoleLabel;
}

/**
 * Count the queries that rendering the matrix runs. Call it once first, and again after changing
 * permissions, so Spatie's permission cache is warm and the measurements compare the matrix only.
 */
function countPermissionMatrixQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(RolePermissionMatrix::class)->assertOk();

    $queryCount = count(DB::connection()->getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('marks exactly the permissions each role grants, system roles first and then custom roles by name', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    RoleFactory::new()->createOne(['label' => 'Zeta']);
    RoleFactory::new()->createOne(['label' => 'Soporte'])->givePermissionTo(AdminPermission::AdminPostsViewAny->value);
    Role::query()->create(['name' => 'custom-web-role', 'guard_name' => 'web']);

    $permissionsByRoleLabel = matrixPermissionsByRoleLabel();

    expect(array_keys($permissionsByRoleLabel))->toBe([
        ...array_map(static fn (AdminRole $adminRole): string => $adminRole->label(), AdminRole::cases()),
        'Soporte',
        'Zeta',
    ])
        ->and($permissionsByRoleLabel['Soporte'])->toBe([AdminPermission::AdminPostsViewAny->value])
        ->and($permissionsByRoleLabel['Zeta'])->toBeEmpty()
        ->and($permissionsByRoleLabel[AdminRole::AdminUsersModerator->label()])->toContain(AdminPermission::AdminUsersSuspend->value)
        ->and($permissionsByRoleLabel[AdminRole::AdminPostsReader->label()])->not->toContain(AdminPermission::AdminUsersSuspend->value)
        ->and($permissionsByRoleLabel[AdminRole::Admin->label()])->toBe([AdminPermission::AdminAccess->value]);
});

it('renders one granted cell per granted permission of the catalog', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $grantedPairs = DB::table('role_has_permissions')->count();

    $html = Livewire::test(RolePermissionMatrix::class)->html();

    expect(substr_count($html, 'data-matrix-cell="granted"'))->toBe($grantedPairs);
});

it('shows the matrix in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    Livewire::test(RolePermissionMatrix::class)
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.matrix.title'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.areas.backups'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.roles.admin_users_moderator.label'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.permissions.admin_users_suspend.label'));
});

it('is linked from the roles list', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    Livewire::test(ListRoles::class)->assertSee(RoleResource::getUrl('matrix'), false);
});

it('denies the matrix to an administrator without admin.roles.viewAny', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersModerator);

    Livewire::test(RolePermissionMatrix::class)->assertForbidden();

    expect(RolePermissionMatrix::canAccess())->toBeFalse();
});

it('runs the same number of queries whatever the number of roles and their permissions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    countPermissionMatrixQueries();
    $queriesBefore = countPermissionMatrixQueries();

    foreach (range(1, 2) as $index) {
        RoleFactory::new()->createOne()
            ->givePermissionTo(AdminPermission::AdminPostsView->value, AdminPermission::AdminUsersView->value);
    }

    countPermissionMatrixQueries();

    expect(countPermissionMatrixQueries())->toBe($queriesBefore);
});

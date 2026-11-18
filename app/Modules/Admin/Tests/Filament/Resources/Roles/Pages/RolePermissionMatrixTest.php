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
use App\Modules\Admin\Tests\Support\QueryLog;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
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
 * The matrix page opened with the given comparison in the URL, signed in as a roles reader.
 *
 * @param  array<mixed>  $queryParameters
 */
function comparedPermissionMatrix(TestCase $testCase, array $queryParameters): RolePermissionMatrix
{
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $component = Livewire::withQueryParams($queryParameters)->test(RolePermissionMatrix::class)->instance();

    throw_unless($component instanceof RolePermissionMatrix, LogicException::class, 'The permission matrix page must be mounted.');

    return $component;
}

/**
 * The names of the permissions the matrix shows as rows.
 *
 * @return list<string>
 */
function matrixRowPermissionNames(RolePermissionMatrix $rolePermissionMatrix): array
{
    $permissionNames = [];

    foreach ($rolePermissionMatrix->getMatrixAreas() as $areaPermissions) {
        foreach ($areaPermissions as $areaPermission) {
            $permissionNames[] = $areaPermission['name'];
        }
    }

    return $permissionNames;
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

    $renderMatrix = static fn (): Testable => Livewire::test(RolePermissionMatrix::class)->assertOk();

    QueryLog::count($renderMatrix);
    $queriesBefore = QueryLog::count($renderMatrix);

    foreach (range(1, 2) as $index) {
        RoleFactory::new()->createOne()
            ->givePermissionTo(AdminPermission::AdminPostsView->value, AdminPermission::AdminUsersView->value);
    }

    QueryLog::count($renderMatrix);

    expect(QueryLog::count($renderMatrix))->toBe($queriesBefore);
});

it('shows only the compared roles from the URL, in display order', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $customRole = RoleFactory::new()->createOne(['label' => 'Soporte']);

    $rolePermissionMatrix = comparedPermissionMatrix($testCase, ['roles' => [$customRole->name, AdminRole::AdminPostsReader->value]]);

    expect(array_column($rolePermissionMatrix->getMatrixRoles(), 'label'))->toBe([AdminRole::AdminPostsReader->label(), 'Soporte'])
        ->and($rolePermissionMatrix->hasInvalidComparison())->toBeFalse()
        ->and(matrixRowPermissionNames($rolePermissionMatrix))->toHaveSameSize(AdminPermission::cases());
});

it('hides the permissions the compared roles agree on and the areas left empty', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $customRole = RoleFactory::new()->createOne(['label' => 'Soporte']);
    $customRole->givePermissionTo(AdminPermission::AdminPostsViewAny->value);

    $rolePermissionMatrix = comparedPermissionMatrix($testCase, [
        'roles' => [AdminRole::AdminPostsReader->value, $customRole->name],
        'only_differences' => '1',
    ]);

    expect(matrixRowPermissionNames($rolePermissionMatrix))->toBe([AdminPermission::AdminPostsView->value])
        ->and(array_keys($rolePermissionMatrix->getMatrixAreas()))->toBe([TranslatedText::of('admin/roles.catalog.areas.posts')]);
});

it('ignores a comparison of the wrong size or with unknown roles and says so', function (array $roleNames): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $testable = Livewire::withQueryParams(['roles' => $roleNames])->test(RolePermissionMatrix::class)
        ->assertOk()
        ->assertSee(TranslatedText::of('admin/roles.compare.invalid_selection', [
            'min' => RolePermissionMatrix::MIN_COMPARED_ROLES,
            'max' => RolePermissionMatrix::MAX_COMPARED_ROLES,
        ]));
    $component = $testable->instance();

    throw_unless($component instanceof RolePermissionMatrix, LogicException::class, 'The permission matrix page must be mounted.');

    expect($component->getMatrixRoles())->toHaveSameSize(AdminRole::cases());
})->with([
    'one role' => [[AdminRole::AdminPostsReader->value]],
    'five roles' => [[
        AdminRole::AdminPostsReader->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminRolesReader->value,
        AdminRole::AdminBackupsReader->value,
        AdminRole::AdminActivityReader->value,
    ]],
    'unknown role' => [[AdminRole::AdminPostsReader->value, 'custom-does-not-exist']],
]);

it('does not warn when nothing is being compared', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    expect(comparedPermissionMatrix($testCase, [])->hasInvalidComparison())->toBeFalse();
});

it('updates the comparison when roles are picked on the page', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    Livewire::test(RolePermissionMatrix::class)
        ->set('roles', [AdminRole::AdminPostsReader->value, AdminRole::AdminUsersReader->value])
        ->set('onlyDifferences', true)
        ->assertSee(AdminPermission::AdminUsersView->label())
        ->assertDontSee(AdminPermission::AdminBackupsView->label());
});

it('marks the sensitive permissions and only them, also when comparing only the differences', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $sensitivePermissionNames = array_map(static fn (AdminPermission $adminPermission): string => $adminPermission->value, AdminPermission::sensitive());
    $fullMatrixHtml = Livewire::test(RolePermissionMatrix::class)->html();

    $comparedTestable = Livewire::withQueryParams([
        'roles' => [AdminRole::AdminUsersReader->value, AdminRole::AdminUsersModerator->value],
        'only_differences' => '1',
    ])->test(RolePermissionMatrix::class);

    $component = $comparedTestable->instance();

    throw_unless($component instanceof RolePermissionMatrix, LogicException::class, 'The permission matrix page must be mounted.');

    $markedPermissionNames = [];
    $shownSensitiveRowCount = 0;

    foreach ($component->getMatrixAreas() as $areaPermissions) {
        foreach ($areaPermissions as $areaPermission) {
            if ($areaPermission['sensitive']) {
                $markedPermissionNames[] = $areaPermission['name'];
                $shownSensitiveRowCount++;
            }
        }
    }

    expect(substr_count($fullMatrixHtml, 'data-matrix-sensitive'))->toBe(\count($sensitivePermissionNames))
        ->and($shownSensitiveRowCount)->toBeGreaterThan(0)
        ->and(array_diff($markedPermissionNames, $sensitivePermissionNames))->toBeEmpty()
        ->and(substr_count($comparedTestable->html(), 'data-matrix-sensitive'))->toBe($shownSensitiveRowCount);
});

it('links each permission row to who holds it, also when comparing', function (array $queryParameters): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    $html = Livewire::withQueryParams($queryParameters)->test(RolePermissionMatrix::class)->html();

    expect(substr_count($html, 'href="'.e(RoleResource::getUrl('access')).'?permission='))->toBe(substr_count($html, 'scope="row"'))
        ->and($html)->toContain('href="'.e(RoleResource::getUrl('access', ['permission' => AdminPermission::AdminUsersSuspend->value])).'"');
})->with([
    'whole matrix' => [[]],
    'only the differences of two roles' => [['roles' => [AdminRole::AdminUsersReader->value, AdminRole::AdminUsersModerator->value], 'only_differences' => '1']],
]);

it('shows the permission rows without links to an administrator who cannot see who holds them', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    Livewire::test(RolePermissionMatrix::class)
        ->assertOk()
        ->assertSee(AdminPermission::AdminUsersSuspend->label())
        ->assertDontSeeHtml('data-matrix-holders-link')
        ->assertDontSeeHtml(RoleResource::getUrl('access'));
});

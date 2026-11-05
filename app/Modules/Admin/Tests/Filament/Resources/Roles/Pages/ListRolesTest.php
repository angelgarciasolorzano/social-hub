<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The roles list with every role on one page.
 *
 * @return Testable<ListRoles>
 */
function rolesListPage(): Testable
{
    return Livewire::test(ListRoles::class)
        ->call('loadTable')
        ->set('tableRecordsPerPage', 50);
}

/**
 * The roles the list shows, keyed by name.
 *
 * @param  Testable<ListRoles>  $testable
 * @return array<string, Role>
 */
function listedRoles(Testable $testable): array
{
    $roles = FilamentTableRecords::visibleRecords(FilamentTableRecords::table($testable), Role::class);

    return array_combine(
        array_map(static fn (Role $role): string => $role->name, $roles),
        $roles,
    );
}

/**
 * Count the queries that rendering the list runs; call it once first so caches are warm.
 */
function countRolesListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    rolesListPage()->assertOk();

    $queryCount = count(DB::connection()->getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lists every cataloged role of the admin guard with its permission and user counts', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    createUser()->assignRole(AdminRole::AdminPostsReader->value);
    createUser()->assignRole(AdminRole::AdminPostsReader->value);
    Role::query()->create(['name' => 'web-only-role', 'guard_name' => 'web']);

    $roles = listedRoles(rolesListPage());

    expect(array_keys($roles))->toEqualCanonicalizing(array_map(
        static fn (AdminRole $adminRole): string => $adminRole->value,
        AdminRole::cases(),
    ))
        ->and($roles[AdminRole::AdminPostsReader->value]->getAttribute('users_count'))->toBe(2)
        ->and($roles[AdminRole::AdminPostsReader->value]->getAttribute('permissions_count'))->toBe(2)
        ->and($roles[AdminRole::AdminRolesManager->value]->getAttribute('permissions_count'))->toBe(8);
});

it('lists a custom role with its label, its description and the custom type', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $customRole = RoleFactory::new()->createOne(['name' => 'custom-soporte', 'label' => 'Soporte', 'description' => 'Atiende a los usuarios.']);

    $testable = rolesListPage()
        ->assertSee('Soporte')
        ->assertSee('custom-soporte')
        ->assertSee('Atiende a los usuarios.')
        ->assertSee(TranslatedText::of('admin/roles.types.custom'))
        ->assertSee(TranslatedText::of('admin/roles.types.system'))
        ->assertDontSee(TranslatedText::of('admin/roles.table.not_editable'));

    expect(listedRoles($testable))->toHaveKey($customRole->name);
});

it('filters the roles by type', function (string $type, bool $expectsSystemRoles): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $customRole = RoleFactory::new()->createOne();

    $testable = rolesListPage();
    $testable->__call('filterTable', ['type', $type]);

    $expectedNames = $expectsSystemRoles
        ? array_map(static fn (AdminRole $adminRole): string => $adminRole->value, AdminRole::cases())
        : [$customRole->name];

    expect(array_keys(listedRoles($testable)))->toEqualCanonicalizing($expectedNames);
})->with([
    'system' => ['system', true],
    'custom' => ['custom', false],
]);

it('finds a custom role by its label', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $customRole = RoleFactory::new()->createOne(['name' => 'custom-auditoria', 'label' => 'Auditoría externa']);

    $testable = rolesListPage()->call('$set', 'tableSearch', 'externa');

    expect(array_keys(listedRoles($testable)))->toBe([$customRole->name]);
});

it('shows the catalog in Spanish and finds a role by its translated name', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $testable = rolesListPage()
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.roles.admin_roles_reader.label'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.roles.admin_roles_reader.description'))
        ->call('$set', 'tableSearch', 'moderador');

    expect(array_keys(listedRoles($testable)))->toBe([AdminRole::AdminUsersModerator->value]);
});

it('links each role to its detail page', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    rolesListPage()->assertSee(
        RoleResource::getUrl('view', ['record' => Role::findByName(AdminRole::AdminUsersModerator->value, 'admin')]),
        false,
    );
});

it('denies the list to an administrator without admin.roles.viewAny', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersModerator);

    Livewire::test(ListRoles::class)->assertForbidden();

    expect(RoleResource::canAccess())->toBeFalse();
});

it('runs the same number of queries whatever the number of users holding roles', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    countRolesListQueries();
    $queriesWithFewUsers = countRolesListQueries();

    foreach (AdminRole::cases() as $adminRole) {
        createUser()->assignRole($adminRole->value);
        createUser()->assignRole($adminRole->value);
    }

    foreach (range(1, 3) as $index) {
        createUser()->assignRole(RoleFactory::new()->createOne());
    }

    countRolesListQueries();

    expect(countRolesListQueries())->toBe($queriesWithFewUsers);
});

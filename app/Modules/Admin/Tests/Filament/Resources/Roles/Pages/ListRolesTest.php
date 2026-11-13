<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Admin\Filament\Resources\Roles\Pages\RolePermissionMatrix;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
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

/**
 * Compare the given roles from the list with the bulk action, signed in as a roles reader.
 *
 * @param  list<Role>  $roles
 * @return Testable<ListRoles>
 */
function compareSelectedRoles(TestCase $testCase, array $roles): Testable
{
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $testable = rolesListPage();
    $testable->__call('selectTableRecords', [array_map(static fn (Role $role): int|string => $role->id, $roles)]);
    $testable->__call('callAction', [TestAction::make('compareSelected')->table()->bulk()]);

    return $testable;
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

it('splits the roles by type in tabs with their counts, all of them by default', function (string $tab, bool $expectsSystemRoles): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $customRole = RoleFactory::new()->createOne();
    Role::query()->create(['name' => 'web-only-role', 'guard_name' => 'web']);

    $testable = Livewire::test(ListRoles::class)
        ->set('activeTab', $tab)
        ->call('loadTable')
        ->set('tableRecordsPerPage', 50);

    $expectedNames = $expectsSystemRoles
        ? array_map(static fn (AdminRole $adminRole): string => $adminRole->value, AdminRole::cases())
        : [$customRole->name];
    $tabs = new ListRoles()->getTabs();

    expect(array_keys(listedRoles($testable)))->toEqualCanonicalizing($expectedNames)
        ->and($tabs['all']->getBadge())->toEqual(count(AdminRole::cases()) + 1)
        ->and($tabs['system']->getBadge())->toEqual(count(AdminRole::cases()))
        ->and($tabs['custom']->getBadge())->toEqual(1);

    rolesListPage()->assertSet('activeTab', 'all');
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

it('links the new role button to the create page for a roles manager', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    $testable = Livewire::test(ListRoles::class);
    $testable->__call('assertActionVisible', ['createRole']);
    $testable->__call('assertActionHasUrl', ['createRole', RoleResource::getUrl('create')]);
});

it('hides the new role button from a roles reader', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $testable = Livewire::test(ListRoles::class);
    $testable->__call('assertActionHidden', ['createRole']);
    $testable->assertDontSee(TranslatedText::of('admin/roles.actions.create.label'));
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
    RoleFactory::new()->createOne();

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

it('links a roles reader to compare roles in the matrix and offers comparing as the only bulk action', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $testable = rolesListPage();
    $testable->__call('assertActionVisible', ['compareRoles']);
    $testable->__call('assertActionHasUrl', ['compareRoles', RoleResource::getUrl('matrix')]);
    $testable->__call('assertActionVisible', [TestAction::make('compareSelected')->table()->bulk()]);

    expect(array_keys(FilamentTableRecords::table($testable)->getTable()->getFlatBulkActions()))->toBe(['compareSelected']);
});

it('opens the selected roles in the matrix showing only their differences', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $roles = [
        RoleFactory::new()->createOne(),
        RoleFactory::new()->createOne(),
        RoleFactory::new()->createOne(),
    ];

    compareSelectedRoles($testCase, $roles)->assertRedirect(RoleResource::getUrl('matrix', [
        'roles' => array_map(static fn (Role $role): string => $role->name, Role::inDisplayOrder($roles)),
        'only_differences' => 1,
    ]));
});

it('does not compare fewer than two or more than four roles and says how many to pick', function (int $roleCount): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $roles = [];

    foreach (range(1, $roleCount) as $index) {
        $roles[] = RoleFactory::new()->createOne();
    }

    compareSelectedRoles($testCase, $roles)->assertNoRedirect();

    Notification::assertNotified(TranslatedText::of('admin/roles.compare.wrong_count', [
        'min' => RolePermissionMatrix::MIN_COMPARED_ROLES,
        'max' => RolePermissionMatrix::MAX_COMPARED_ROLES,
    ]));
})->with([1, 5]);

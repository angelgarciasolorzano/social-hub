<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\PermissionHolders;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The mounted page component of a test.
 *
 * @param  Testable<Component>  $testable
 */
function permissionHoldersInstance(Testable $testable): PermissionHolders
{
    $component = $testable->instance();

    return $component instanceof PermissionHolders
        ? $component
        : throw new LogicException('The permission holders page must be mounted.');
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('is served at /admin/roles/access, not as a role record', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    expect(parse_url(RoleResource::getUrl('access'), PHP_URL_PATH))->toBe('/admin/roles/access')
        ->and(PermissionHolders::urlFor(AdminPermission::AdminBackupsDownload))->toBe(RoleResource::getUrl('access', ['permission' => 'admin.backups.download']));
});

it('lists exactly the roles of the admin guard that grant the permission, in display order, with their users', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    $role = adminCatalogRole(AdminRole::AdminBackupsOperator);
    createUserHoldingRoles($role);
    createUserHoldingRoles($role);
    RoleFactory::new()->createOne(['label' => 'Soporte'])->givePermissionTo(AdminPermission::AdminBackupsView->value, AdminPermission::AdminBackupsDownload->value);
    RoleFactory::new()->createOne(['label' => 'Auditoría'])->givePermissionTo(AdminPermission::AdminBackupsView->value);
    $webPermission = Permission::query()->create(['name' => AdminPermission::AdminBackupsDownload->value, 'guard_name' => 'web']);
    Role::query()->create(['name' => 'web-backups', 'guard_name' => 'web'])->givePermissionTo($webPermission);

    $holderRoles = permissionHoldersInstance(permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value))->getHolderRoles();

    expect(array_map(static fn (array $holderRole): array => [$holderRole['label'], $holderRole['isSystem'], $holderRole['usersCount']], $holderRoles))->toBe([
        [AdminRole::AdminBackupsOperator->label(), true, 2],
        ['Soporte', false, 0],
    ])
        ->and($holderRoles[0]['url'])->toBe(RoleResource::getUrl('view', ['record' => $role]));
});

it('says so when no role grants the permission', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    adminCatalogRole(AdminRole::AdminBackupsOperator)->revokePermissionTo(AdminPermission::AdminBackupsDownload->value);

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->assertOk()
        ->assertSeeHtml('data-access-roles-empty')
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.access.roles.empty'))
        ->assertDontSeeHtml('data-access-role=');
});

it('leaves the selector empty for a value that is not a cataloged permission', function (string $permission): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    $testable = permissionHoldersPageFor($permission)
        ->assertOk()
        ->assertSet('permission', null)
        ->assertSeeHtml('data-access-choose')
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.access.choose'));

    expect(permissionHoldersInstance($testable)->getHolderRoles())
        ->toBeEmpty();
})->with([
    'unknown value' => ['admin.nothing'],
    'empty value' => [''],
    'role name' => [AdminRole::AdminBackupsOperator->value],
]);

it('shows the holders of a permission picked on the page', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    permissionHoldersPageFor(null)
        ->assertSeeHtml('data-access-choose')
        ->set('permission', AdminPermission::AdminBackupsDownload->value)
        ->assertDontSeeHtml('data-access-choose')
        ->assertSeeHtml('data-access-role="'.AdminRole::AdminBackupsOperator->value.'"');
});

it('denies the page to an administrator who cannot list roles or users', function (AdminRole $adminRole): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, $adminRole);

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)->assertForbidden();

    expect(PermissionHolders::canAccess())->toBeFalse()
        ->and(PermissionHolders::urlFor(AdminPermission::AdminBackupsDownload))->toBeNull();
})->with([
    'without users viewAny' => [AdminRole::AdminRolesReader],
    'without roles viewAny' => [AdminRole::AdminUsersReader],
]);

it('counts the holders who are suspended or lack the panel MFA and warns about them', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    $role = adminCatalogRole(AdminRole::AdminBackupsOperator);
    $customRole = RoleFactory::new()->createOne(['label' => 'Soporte']);
    $customRole->givePermissionTo(AdminPermission::AdminBackupsView->value, AdminPermission::AdminBackupsDownload->value);

    $user = createSuspendedUser();
    $user->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();
    $user->assignRole($role, $customRole);
    createUserHoldingRoles($role);
    createUserHoldingRoles($customRole)->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();

    $testable = permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->assertSeeHtml('data-access-warnings')
        ->assertSeeHtml('data-access-warning="suspended"')
        ->assertSeeHtml('data-access-warning="without-mfa"');

    expect(permissionHoldersInstance($testable)->getHolderSummary())->toBe(['holders' => 3, 'suspended' => 1, 'withoutMfa' => 1]);
});

it('does not warn when every holder can use the permission', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    createUserHoldingRoles(adminCatalogRole(AdminRole::AdminBackupsOperator))->forceFill(['app_authentication_secret' => 'PANELSECRET'])->save();

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)->assertDontSeeHtml('data-access-warnings');
});

it('marks a sensitive permission and only those', function (AdminPermission $adminPermission): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    $testable = permissionHoldersPageFor($adminPermission->value);

    if ($adminPermission->isSensitive()) {
        $testable->assertSeeHtml('data-access-sensitive');
    } else {
        $testable->assertDontSeeHtml('data-access-sensitive');
    }
})->with([
    'sensitive' => [AdminPermission::AdminBackupsDownload],
    'not sensitive' => [AdminPermission::AdminPostsView],
]);

it('links the permissions the chosen one needs and the ones that need it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    $requirements = permissionHoldersInstance(permissionHoldersPageFor(AdminPermission::AdminUsersView->value))->getRequirements();

    expect($requirements['requires'])->toBe([[
        'label' => AdminPermission::AdminUsersViewAny->label(),
        'url' => PermissionHolders::urlFor(AdminPermission::AdminUsersViewAny),
    ]])
        ->and(array_column($requirements['dependents'], 'label'))->toBe(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->label(),
            AdminPermission::AdminUsersView->dependents(),
        ))
        ->and($requirements['dependents'])->not->toBeEmpty();
});

it('shows the page in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    permissionHoldersPageFor(AdminPermission::AdminBackupsDownload->value)
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.access.title'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.access.roles.heading'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.access.requirements.requires'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.permissions.admin_backups_download.description'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.roles.admin_backups_operator.label'));
});

<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return Testable<ViewRole>
 */
function viewRolePage(Role $role): Testable
{
    return Livewire::test(ViewRole::class, ['record' => $role->getKey()]);
}

function adminGuardRole(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', 'admin')
        ->sole();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows the role and its permissions grouped by area', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $text = static fn (string $key): string => translatedTextIn($preferredLocale, 'admin/roles.'.$key);

    viewRolePage(adminGuardRole(AdminRole::AdminRolesManager))
        ->assertOk()
        ->assertSee($text('catalog.roles.admin_roles_manager.label'))
        ->assertSee(AdminRole::AdminRolesManager->value)
        ->assertSee($text('catalog.roles.admin_roles_manager.description'))
        ->assertSee($text('catalog.areas.users'))
        ->assertSee($text('catalog.areas.roles'))
        ->assertSee($text('catalog.permissions.'.AdminPermission::AdminUsersRoles->translationKey().'.label'))
        ->assertSee($text('catalog.permissions.'.AdminPermission::AdminRolesUpdate->translationKey().'.label'))
        ->assertDontSee($text('catalog.areas.backups'))
        ->assertDontSee($text('catalog.permissions.'.AdminPermission::AdminAccess->translationKey().'.label'));
})->with([PreferredLocale::ENGLISH, PreferredLocale::SPANISH]);

it('titles the page and breadcrumb with the translated role name', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = adminGuardRole(AdminRole::AdminPostsReader);
    $label = translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.roles.admin_posts_reader.label');

    expect(RoleResource::getRecordTitle($role))->toBe($label)
        ->and(RoleResource::getRecordTitle(Role::query()->create(['name' => 'legacy-role', 'guard_name' => 'admin'])))->toBe('legacy-role');

    viewRolePage($role)->assertSee($label);
});

it('shows an empty state for a role outside the catalog without permissions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = Role::query()->create(['name' => 'legacy-role', 'guard_name' => 'admin']);

    viewRolePage($role)
        ->assertOk()
        ->assertSee('legacy-role')
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.table.not_editable_tooltip'))
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.infolist.permissions.empty'));
});

it('does not open a role of another guard', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = Role::query()->create(['name' => AdminRole::AdminUsersReader->value.'-web', 'guard_name' => 'web']);

    viewRolePage($role)->assertNotFound();
});

it('denies the detail to an administrator without admin.roles.view', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersModerator);

    viewRolePage(adminGuardRole(AdminRole::AdminPostsReader))->assertForbidden();
});

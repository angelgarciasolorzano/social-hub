<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Support\Translation\TranslatedText;
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

it('shows a custom role with its label, identifier, type and an empty state without permissions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = RoleFactory::new()->createOne(['name' => 'custom-soporte', 'label' => 'Soporte', 'description' => 'Atiende a los usuarios.']);

    viewRolePage($role)
        ->assertOk()
        ->assertSee('Soporte')
        ->assertSee('custom-soporte')
        ->assertSee('Atiende a los usuarios.')
        ->assertSee(TranslatedText::of('admin/roles.types.custom'))
        ->assertSee(TranslatedText::of('admin/roles.infolist.permissions.empty'))
        ->assertDontSee(TranslatedText::of('admin/roles.locked.notice'));

    expect(RoleResource::getRecordTitle($role))->toBe('Soporte');
});

it('shows that the admin role is locked', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    viewRolePage(adminGuardRole(AdminRole::Admin))
        ->assertOk()
        ->assertSee(TranslatedText::of('admin/roles.locked.notice'))
        ->assertSee(TranslatedText::of('admin/roles.types.system'));

    viewRolePage(adminGuardRole(AdminRole::AdminPostsReader))
        ->assertDontSee(TranslatedText::of('admin/roles.locked.notice'));
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

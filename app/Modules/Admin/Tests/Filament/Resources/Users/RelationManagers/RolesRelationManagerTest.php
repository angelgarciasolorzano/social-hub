<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\RolesRelationManager;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return Testable<Component>
 */
function userRolesRelationManagerFor(User $user): Testable
{
    return Livewire::test(RolesRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ]);
}

function userAdminRole(AdminRole $adminRole): Role
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

it('lists only the admin roles of the user with their permissions', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersModerator->value);

    createUser()->assignRole(AdminRole::AdminBackupsOperator->value);
    Role::query()->create(['name' => 'web-member', 'guard_name' => 'web']);

    $testable = userRolesRelationManagerFor($user)
        ->assertOk()
        ->assertSee(translatedTextIn($preferredLocale, 'admin/roles.catalog.roles.admin_users_moderator.label'))
        ->assertSee(translatedTextIn($preferredLocale, 'admin/roles.catalog.permissions.admin_users_suspend.label'));

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), Role::class))
        ->toBe([FilamentTableRecords::recordKey(userAdminRole(AdminRole::AdminUsersModerator))]);
})->with([PreferredLocale::ENGLISH, PreferredLocale::SPANISH]);

it('offers no write actions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $user = createUser();
    $user->assignRole(AdminRole::AdminPostsReader->value);

    $table = FilamentTableRecords::table(userRolesRelationManagerFor($user))->getTable();

    expect($table->getHeaderActions())->toBeEmpty()
        ->and($table->getRecordActions())->toBeEmpty()
        ->and($table->getToolbarActions())->toBeEmpty();
});

it('hides the tab and the effective permissions from an administrator who cannot list roles', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader);
    $user = createUser();
    $user->assignRole(AdminRole::AdminPostsReader->value);

    expect(RolesRelationManager::canViewForRecord($user, ViewUser::class))->toBeFalse();

    userRolesRelationManagerFor($user)->assertForbidden();

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertDontSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.users.effective_permissions.heading'));
});

it('shows the effective permissions of two roles once each, grouped by area', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value, AdminRole::AdminUsersModerator->value);

    $text = static fn (string $key): string => translatedTextIn($preferredLocale, 'admin/roles.'.$key);
    $html = Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertSee($text('users.effective_permissions.heading'))
        ->assertSee($text('catalog.areas.users'))
        ->assertSee($text('catalog.permissions.admin_users_reactivate.label'))
        ->assertDontSee($text('catalog.permissions.admin_backups_download.label'))
        ->html();

    expect(substr_count($html, e($text('catalog.permissions.admin_users_view.label'))))->toBe(1);
})->with([PreferredLocale::ENGLISH, PreferredLocale::SPANISH]);

it('explains when the user has no admin permissions', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);

    Livewire::test(ViewUser::class, ['record' => createUser()->id])
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.users.effective_permissions.empty'));
});

it('lists a custom role with its label and description', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    $customRole = RoleFactory::new()->createOne(['name' => 'custom-soporte', 'label' => 'Soporte', 'description' => 'Atiende a los usuarios.']);
    $user = createUser();
    $user->assignRole($customRole);

    $testable = userRolesRelationManagerFor($user)
        ->assertOk()
        ->assertSee('Soporte')
        ->assertSee('custom-soporte')
        ->assertSee('Atiende a los usuarios.');

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), Role::class))
        ->toBe([FilamentTableRecords::recordKey($customRole)]);
});

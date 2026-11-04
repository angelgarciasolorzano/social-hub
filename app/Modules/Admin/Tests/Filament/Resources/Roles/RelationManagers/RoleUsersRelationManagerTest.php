<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleUsersRelationManager;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

function roleWithUsers(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', 'admin')
        ->sole();
}

/**
 * @return Testable<Component>
 */
function roleUsersRelationManagerFor(Role $role): Testable
{
    return Livewire::test(RoleUsersRelationManager::class, [
        'ownerRecord' => $role,
        'pageClass' => ViewRole::class,
    ]);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lists only the users holding the role, linked to their detail', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $user = createUser();
    $user->assignRole(AdminRole::AdminPostsReader->value);

    $suspendedPostsReader = createSuspendedUser();
    $suspendedPostsReader->assignRole(AdminRole::AdminPostsReader->value);

    createUser()->assignRole(AdminRole::AdminUsersReader->value);

    $testable = roleUsersRelationManagerFor(roleWithUsers(AdminRole::AdminPostsReader))
        ->assertOk()
        ->assertSee($user->email)
        ->assertSee('Suspended')
        ->assertSee(UserResource::getUrl('view', ['record' => $user]), false);

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), User::class))
        ->toEqualCanonicalizing([
            FilamentTableRecords::recordKey($user),
            FilamentTableRecords::recordKey($suspendedPostsReader),
        ]);
});

it('offers no create, attach, detach or edit actions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    $table = FilamentTableRecords::table(roleUsersRelationManagerFor(roleWithUsers(AdminRole::AdminPostsReader)))->getTable();

    expect($table->getHeaderActions())->toBeEmpty()
        ->and($table->getRecordActions())->toBeEmpty()
        ->and($table->getToolbarActions())->toBeEmpty();
});

it('shows an empty state in Spanish for a role nobody holds', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    roleUsersRelationManagerFor(roleWithUsers(AdminRole::AdminBackupsOperator))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.relations.users.empty'));

    expect(RoleUsersRelationManager::getTitle(roleWithUsers(AdminRole::AdminBackupsOperator), ViewRole::class))
        ->toBe(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.relations.users.title'));
});

it('hides the tab from a roles reader who cannot list users', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = roleWithUsers(AdminRole::AdminPostsReader);

    expect(RoleUsersRelationManager::canViewForRecord($role, ViewRole::class))->toBeFalse();

    roleUsersRelationManagerFor($role)->assertForbidden();
});

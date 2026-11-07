<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\CreateRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\EditRole;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Support\Translation\TranslatedText;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The create page with a role named and the given users permissions checked, ready to save.
 *
 * @param  list<AdminPermission>  $usersPermissions
 * @return Testable<CreateRole>
 */
function filledCreateRolePage(array $usersPermissions): Testable
{
    return Livewire::test(CreateRole::class)
        ->set('data.label', 'Soporte')
        ->set('data.permissions.users', array_map(static fn (AdminPermission $adminPermission): string => $adminPermission->value, $usersPermissions))
        ->set('data.current_password', 'password');
}

/**
 * The edit page of the role with the given users permissions checked, ready to save.
 *
 * @param  list<AdminPermission>  $usersPermissions
 * @return Testable<EditRole>
 */
function filledEditRolePage(Role $role, array $usersPermissions): Testable
{
    return Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->set('data.permissions.users', array_map(static fn (AdminPermission $adminPermission): string => $adminPermission->value, $usersPermissions))
        ->set('data.current_password', 'password');
}

/**
 * The permissions of the users area that hand over the roles of other users.
 *
 * @return list<AdminPermission>
 */
function usersRolesPermissions(): array
{
    return [AdminPermission::AdminUsersViewAny, AdminPermission::AdminUsersView, AdminPermission::AdminUsersRoles];
}

/**
 * The names of the permissions the role holds, sorted.
 *
 * @return list<string>
 */
function acknowledgedRolePermissionNames(Role $role): array
{
    /** @var list<string> $permissionNames */
    $permissionNames = $role->refresh()->permissions->pluck('name')->sort()->values()->all();

    return $permissionNames;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
});

it('does not create a role with a sensitive permission until it is acknowledged', function (): void {
    filledCreateRolePage(usersRolesPermissions())
        ->assertSee(TranslatedText::of('admin/roles.form.acknowledge_sensitive'))
        ->call('create')
        ->assertHasErrors(['data.acknowledge_sensitive' => TranslatedText::of('admin/roles.form.acknowledge_sensitive_required')]);

    expect(Role::query()->where('is_system', false)->exists())->toBeFalse();

    filledCreateRolePage(usersRolesPermissions())
        ->set('data.acknowledge_sensitive', true)
        ->call('create')
        ->assertHasNoErrors();

    expect(Role::query()->where('is_system', false)->sole()->hasPermissionTo(AdminPermission::AdminUsersRoles->value))->toBeTrue();
});

it('does not ask to acknowledge a new role without sensitive permissions', function (): void {
    filledCreateRolePage([AdminPermission::AdminUsersViewAny])
        ->assertDontSee(TranslatedText::of('admin/roles.form.acknowledge_sensitive'))
        ->call('create')
        ->assertHasNoErrors();

    expect(Role::query()->where('is_system', false)->exists())->toBeTrue();
});

it('does not grant a new sensitive permission to a role until it is acknowledged', function (): void {
    $role = RoleFactory::new()->createOne();

    filledEditRolePage($role, usersRolesPermissions())
        ->call('save')
        ->assertHasErrors(['data.acknowledge_sensitive']);

    expect(acknowledgedRolePermissionNames($role))
        ->toBeEmpty();

    filledEditRolePage($role, usersRolesPermissions())
        ->set('data.acknowledge_sensitive', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(acknowledgedRolePermissionNames($role))->toContain(AdminPermission::AdminUsersRoles->value);
});

it('does not ask to acknowledge keeping or removing a sensitive permission the role had', function (string $change): void {
    $role = RoleFactory::new()->createOne();
    $role->givePermissionTo(array_map(static fn (AdminPermission $adminPermission): string => $adminPermission->value, usersRolesPermissions()));

    $testable = $change === 'keep'
        ? filledEditRolePage($role, usersRolesPermissions())->set('data.permissions.posts', [AdminPermission::AdminPostsViewAny->value])
        : filledEditRolePage($role, [AdminPermission::AdminUsersViewAny, AdminPermission::AdminUsersView]);

    $testable
        ->assertDontSee(TranslatedText::of('admin/roles.form.acknowledge_sensitive'))
        ->call('save')
        ->assertHasNoErrors();

    expect(acknowledgedRolePermissionNames($role))->toHaveCount($change === 'keep' ? 4 : 2);
})->with(['keep', 'remove']);

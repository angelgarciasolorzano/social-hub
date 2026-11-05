<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Database\Seeders\AdminCustomRoleSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seed the catalog, Angel and a few other administrators for the custom roles to be given to.
 */
function seedCustomRoleHolders(TestCase $testCase): User
{
    $testCase->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->forceFill(['email' => UserSeeder::TEST_USER_EMAIL])->save();
    $user->assignRole(AdminRole::Admin->value);

    foreach (range(1, 5) as $index) {
        createUser()->assignRole(AdminRole::Admin->value);
    }

    createUser();

    return $user;
}

/**
 * The cataloged permissions a role holds.
 *
 * @return list<AdminPermission>
 */
function seededCustomRolePermissions(Role $role): array
{
    $adminPermissions = [];

    foreach ($role->permissions as $permission) {
        $adminPermissions[] = AdminPermission::from($permission->name);
    }

    return $adminPermissions;
}

it('creates the three sample custom roles with complete permissions, held by administrators other than Angel', function (): void {
    $user = seedCustomRoleHolders($this);

    $this->seed(AdminCustomRoleSeeder::class);

    $customRoles = Role::query()->where('is_system', false)->with(['permissions', 'users'])->orderBy('id')->get();

    expect($customRoles->pluck('name')->all())->toBe(['custom-soporte', 'custom-editor-de-contenido', 'custom-auditor'])
        ->and($customRoles->pluck('label')->all())->toBe(['Soporte', 'Editor de contenido', 'Auditor']);

    foreach ($customRoles as $customRole) {
        expect($customRole->guard_name)->toBe(Role::GUARD_NAME)
            ->and($customRole->description)->not->toBeEmpty()
            ->and(AdminPermission::missingRequirements(seededCustomRolePermissions($customRole)))->toBeEmpty()
            ->and($customRole->users->count())->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3)
            ->and($customRole->users->modelKeys())->not->toContain($user->id);

        foreach ($customRole->users as $holder) {
            expect($holder instanceof User && $holder->hasRole(AdminRole::Admin->value, Role::GUARD_NAME))->toBeTrue();
        }
    }

    $contentEditorRole = Role::query()->where('name', 'custom-editor-de-contenido')->sole();

    expect(seededCustomRolePermissions($contentEditorRole))->not->toContain(AdminPermission::AdminAnnouncementsDelete)
        ->and(ActivityLog::query()->exists())->toBeFalse();
});

it('changes nothing when it runs again', function (): void {
    seedCustomRoleHolders($this);

    $this->seed(AdminCustomRoleSeeder::class);

    $roleCount = Role::query()->count();
    $assignmentCount = DB::table('model_has_roles')->count();
    $grantCount = DB::table('role_has_permissions')->count();

    $this->seed(AdminCustomRoleSeeder::class);

    expect(Role::query()->count())->toBe($roleCount)
        ->and(Role::query()->where('is_system', false)->count())->toBe(3)
        ->and(DB::table('model_has_roles')->count())->toBe($assignmentCount)
        ->and(DB::table('role_has_permissions')->count())->toBe($grantCount);
});

it('does nothing without the authorization catalog', function (): void {
    $this->seed(AdminCustomRoleSeeder::class);

    expect(Role::query()->exists())->toBeFalse();
});

it('does nothing outside local and testing', function (): void {
    seedCustomRoleHolders($this);
    $this->app->instance('env', 'production');

    app()->call([new AdminCustomRoleSeeder, 'run']);

    expect(Role::query()->where('is_system', false)->exists())->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Policies\RolePolicy;
use App\User\Enums\PreferredLocale;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * The factory of a custom role, or of the given catalog role.
 */
function roleFactory(?AdminRole $adminRole = null): RoleFactory
{
    /** @var RoleFactory $roleFactory */
    $roleFactory = Role::factory();

    return $adminRole instanceof AdminRole ? $roleFactory->system($adminRole) : $roleFactory;
}

it('adds the presentation columns to the roles table', function (): void {
    expect(Schema::hasColumns('roles', ['is_system', 'label', 'description']))->toBeTrue();
});

it('creates a role that is custom and without texts by default', function (): void {
    $role = Role::query()->create(['name' => 'custom-auditor', 'guard_name' => Role::GUARD_NAME])->refresh();

    expect($role->is_system)->toBeFalse()
        ->and($role->label)->toBeNull()
        ->and($role->description)->toBeNull();
});

it('casts is_system to a boolean', function (): void {
    $role = roleFactory(AdminRole::AdminPostsReader)->createOne()->refresh();

    expect($role->is_system)->toBeTrue()
        ->and($role->isSystem())->toBeTrue();
});

it('limits the admin scope to the roles of the admin guard', function (): void {
    roleFactory()->createOne(['name' => 'custom-admin-guard']);
    roleFactory()->createOne(['name' => 'custom-web-guard', 'guard_name' => 'web']);

    expect(Role::query()->admin()->pluck('name')->all())->toBe(['custom-admin-guard']);
});

it('splits system and custom roles by is_system', function (): void {
    roleFactory(AdminRole::AdminPostsReader)->createOne();
    roleFactory()->createOne(['name' => 'custom-auditor']);

    expect(Role::query()->system()->pluck('name')->all())->toBe([AdminRole::AdminPostsReader->value])
        ->and(Role::query()->custom()->pluck('name')->all())->toBe(['custom-auditor']);
});

it('locks only the admin role of the admin guard', function (): void {
    expect(roleFactory(AdminRole::Admin)->createOne()->isLocked())->toBeTrue()
        ->and(roleFactory(AdminRole::Admin)->createOne(['guard_name' => 'web'])->isLocked())->toBeFalse()
        ->and(roleFactory(AdminRole::AdminRolesManager)->createOne()->isLocked())->toBeFalse()
        ->and(roleFactory()->createOne()->isLocked())->toBeFalse();
});

it('returns the catalog case only for a system role', function (): void {
    expect(roleFactory(AdminRole::AdminPostsReader)->makeOne()->adminRole())->toBe(AdminRole::AdminPostsReader)
        ->and(roleFactory()->makeOne(['name' => AdminRole::AdminPostsReader->value])->adminRole())->toBeNull();
});

it('shows a system role with the catalog texts of the current language', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    $role = roleFactory(AdminRole::AdminPostsReader)->makeOne();

    expect($role->displayName())->toBe(translatedTextIn($preferredLocale, 'admin/roles.catalog.roles.admin_posts_reader.label'))
        ->and($role->displayDescription())->toBe(translatedTextIn($preferredLocale, 'admin/roles.catalog.roles.admin_posts_reader.description'));
})->with([PreferredLocale::ENGLISH, PreferredLocale::SPANISH]);

it('shows a custom role with the texts it was saved with', function (): void {
    $role = roleFactory()->makeOne(['label' => 'Auditor', 'description' => 'Reviews the activity log.']);

    expect($role->displayName())->toBe('Auditor')
        ->and($role->displayDescription())->toBe('Reviews the activity log.');
});

it('falls back to the stored name for a role without a label', function (): void {
    $role = roleFactory()->withoutLabel()->makeOne(['name' => 'custom-auditor', 'description' => null]);

    expect($role->displayName())->toBe('custom-auditor')
        ->and($role->displayDescription())->toBeNull();
});

it('is the role model of Spatie Permission', function (): void {
    expect(resolve(PermissionRegistrar::class)->getRoleClass())->toBe(Role::class);
});

it('assigns and checks roles through Spatie with the new model', function (): void {
    $role = roleFactory()->createOne(['name' => 'custom-auditor']);
    $user = createUser();

    $user->assignRole($role);

    expect($user->hasRole('custom-auditor', Role::GUARD_NAME))->toBeTrue()
        ->and($user->fresh()?->roles->first())->toBeInstanceOf(Role::class);
});

it('resolves the role morph to the new model', function (): void {
    expect(Relation::getMorphedModel('role'))->toBe(Role::class);
});

it('resolves RolePolicy for the new model and for Spatie role', function (): void {
    expect(Gate::getPolicyFor(Role::class))->toBeInstanceOf(RolePolicy::class)
        ->and(Gate::getPolicyFor(SpatieRole::class))->toBeInstanceOf(RolePolicy::class);
});

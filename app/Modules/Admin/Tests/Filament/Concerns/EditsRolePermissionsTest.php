<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Concerns\EditsRolePermissions;
use App\Modules\Admin\Models\Role;
use App\User\Enums\PreferredLocale;
use Filament\Forms\Components\CheckboxList;

/*
 * The concern is used by anonymous classes: a named class in a test file would break Composer's PSR-4
 * autoload map. Each helper builds its own, so the result keeps a type PHPStan can follow.
 */

/**
 * @return list<CheckboxList>
 */
function editorPermissionCheckboxLists(): array
{
    return (new class
    {
        use EditsRolePermissions;

        /**
         * @return list<CheckboxList>
         */
        public function checkboxLists(): array
        {
            return self::permissionCheckboxLists();
        }
    })->checkboxLists();
}

/**
 * @return array<string, list<string>>
 */
function editorPermissionsByArea(?Role $role): array
{
    return (new class
    {
        use EditsRolePermissions;

        /**
         * @return array<string, list<string>>
         */
        public function byArea(?Role $role): array
        {
            return self::permissionsByArea($role);
        }
    })->byArea($role);
}

/**
 * @return list<AdminPermission>
 */
function editorSelectedPermissions(mixed $permissionsByArea): array
{
    return (new class
    {
        use EditsRolePermissions;

        /**
         * @return list<AdminPermission>
         */
        public function selected(mixed $permissionsByArea): array
        {
            return self::selectedPermissions($permissionsByArea);
        }
    })->selected($permissionsByArea);
}

/**
 * @param  array<string, list<string>>  $before
 * @param  array<string, list<string>>  $after
 * @return array<string, list<string>>
 */
function editorAppliedDependencies(array $before, array $after): array
{
    return (new class
    {
        use EditsRolePermissions;

        /**
         * @param  array<string, list<string>>  $before
         * @param  array<string, list<string>>  $after
         * @return array<string, list<string>>
         */
        public function applied(array $before, array $after): array
        {
            return self::applyPermissionDependencies($before, $after);
        }
    })->applied($before, $after);
}

/**
 * @param  list<AdminPermission>  $before
 * @param  list<AdminPermission>  $after
 */
function editorChangeSummary(array $before, array $after): string
{
    return (new class
    {
        use EditsRolePermissions;

        /**
         * @param  list<AdminPermission>  $before
         * @param  list<AdminPermission>  $after
         */
        public function summary(array $before, array $after): string
        {
            return self::permissionChangeSummary($before, $after);
        }
    })->summary($before, $after);
}

/**
 * A form state with the given permissions checked.
 *
 * @return array<string, list<string>>
 */
function permissionFormState(AdminPermission ...$adminPermissions): array
{
    $permissionsByArea = [];

    foreach ($adminPermissions as $adminPermission) {
        $permissionsByArea[$adminPermission->area()][] = $adminPermission->value;
    }

    return $permissionsByArea;
}

it('builds one live checkbox list per area with every permission and its description', function (): void {
    $checkboxLists = editorPermissionCheckboxLists();

    $optionValues = [];

    foreach ($checkboxLists as $checkboxList) {
        expect($checkboxList->isLive())->toBeTrue();

        $optionValues = [...$optionValues, ...array_keys($checkboxList->getOptions())];
    }

    expect(array_map(static fn (CheckboxList $checkboxList): string => $checkboxList->getName(), $checkboxLists))
        ->toBe(['permissions.access', 'permissions.dashboard', 'permissions.users', 'permissions.posts', 'permissions.activity', 'permissions.backups', 'permissions.announcements', 'permissions.roles'])
        ->and($optionValues)->toBe(array_map(static fn (AdminPermission $adminPermission): string => $adminPermission->value, AdminPermission::cases()));
});

it('checks the requirements of a permission when it is checked', function (): void {
    $permissionsByArea = editorAppliedDependencies(
        permissionFormState(),
        permissionFormState(AdminPermission::AdminUsersSuspend),
    );

    expect($permissionsByArea['users'])->toBe([
        AdminPermission::AdminUsersViewAny->value,
        AdminPermission::AdminUsersView->value,
        AdminPermission::AdminUsersSuspend->value,
    ]);
});

it('unchecks the dependents of a permission when it is unchecked, and leaves the other areas alone', function (): void {
    $before = permissionFormState(
        AdminPermission::AdminUsersViewAny,
        AdminPermission::AdminUsersView,
        AdminPermission::AdminUsersSuspend,
        AdminPermission::AdminUsersCreate,
        AdminPermission::AdminPostsViewAny,
    );
    $after = permissionFormState(
        AdminPermission::AdminUsersView,
        AdminPermission::AdminUsersSuspend,
        AdminPermission::AdminUsersCreate,
        AdminPermission::AdminPostsViewAny,
    );

    $permissionsByArea = editorAppliedDependencies($before, $after);

    expect($permissionsByArea['users'])->toBeEmpty()
        ->and($permissionsByArea['posts'])->toBe([AdminPermission::AdminPostsViewAny->value]);
});

it('keeps an unchecked permission unchecked even when the rest of the selection required it', function (): void {
    $permissionsByArea = editorAppliedDependencies(
        permissionFormState(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload),
        permissionFormState(AdminPermission::AdminBackupsDownload),
    );

    expect($permissionsByArea['backups'])->toBeEmpty();
});

it('summarizes the grants and revocations with translated names', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    $summary = editorChangeSummary(
        [AdminPermission::AdminPostsViewAny, AdminPermission::AdminPostsView],
        [AdminPermission::AdminPostsViewAny, AdminPermission::AdminDashboardView, AdminPermission::AdminActivityViewAny],
    );

    $expectedGrant = trans_choice('admin/roles.summary.grant', 2, [
        'count' => 2,
        'list' => AdminPermission::AdminDashboardView->label().', '.AdminPermission::AdminActivityViewAny->label(),
    ]);
    $expectedRevoke = trans_choice('admin/roles.summary.revoke', 1, [
        'count' => 1,
        'list' => AdminPermission::AdminPostsView->label(),
    ]);

    expect($summary)->toBe($expectedGrant.'. '.$expectedRevoke.'.')
        ->and($summary)->not->toContain('admin/roles.');
})->with([PreferredLocale::ENGLISH, PreferredLocale::SPANISH]);

it('says nothing changes when the selections are equal', function (): void {
    expect(editorChangeSummary([AdminPermission::AdminAccess], [AdminPermission::AdminAccess]))
        ->toBe(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.summary.none'));
});

it('ignores selected values outside the catalog and returns the catalog order', function (): void {
    expect(editorSelectedPermissions([
        'users' => [AdminPermission::AdminUsersView->value, 'admin.users.forget', 7],
        'access' => [AdminPermission::AdminAccess->value],
        'unknown' => 'admin.dashboard.view',
    ]))->toBe([AdminPermission::AdminAccess, AdminPermission::AdminUsersView])
        ->and(editorSelectedPermissions('admin.access'))->toBeEmpty();
});

it('groups the permissions of a role by area, and starts a new role empty', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
    $role = RoleFactory::new()->createOne();
    $role->givePermissionTo([AdminPermission::AdminPostsView->value, AdminPermission::AdminPostsViewAny->value]);

    $permissionsByArea = editorPermissionsByArea($role);
    $emptyState = editorPermissionsByArea(null);

    expect($permissionsByArea['posts'])->toBe([AdminPermission::AdminPostsViewAny->value, AdminPermission::AdminPostsView->value])
        ->and(array_merge(...array_values($permissionsByArea)))->toHaveCount(2)
        ->and(array_filter($emptyState))->toBeEmpty()
        ->and(array_keys($emptyState))->toBe(array_keys($permissionsByArea));
});

<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Tests\Support\RolePermissionsEditor;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The description each checkbox shows, by permission value, as plain text.
 *
 * @return array<string, string>
 */
function editorPermissionDescriptions(): array
{
    $descriptions = [];

    foreach (RolePermissionsEditor::permissionCheckboxLists() as $checkboxList) {
        foreach ($checkboxList->getDescriptions() as $permissionValue => $description) {
            $descriptions[(string) $permissionValue] = $description instanceof Htmlable ? $description->toHtml() : $description;
        }
    }

    return $descriptions;
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
    $checkboxLists = RolePermissionsEditor::permissionCheckboxLists();

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
    $permissionsByArea = RolePermissionsEditor::applyPermissionDependencies(
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

    $permissionsByArea = RolePermissionsEditor::applyPermissionDependencies($before, $after);

    expect($permissionsByArea['users'])->toBeEmpty()
        ->and($permissionsByArea['posts'])->toBe([AdminPermission::AdminPostsViewAny->value]);
});

it('keeps an unchecked permission unchecked even when the rest of the selection required it', function (): void {
    $permissionsByArea = RolePermissionsEditor::applyPermissionDependencies(
        permissionFormState(AdminPermission::AdminBackupsView, AdminPermission::AdminBackupsDownload),
        permissionFormState(AdminPermission::AdminBackupsDownload),
    );

    expect($permissionsByArea['backups'])->toBeEmpty();
});

it('summarizes the grants and revocations with translated names', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    $summary = RolePermissionsEditor::permissionChangeSummary(
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
    expect(RolePermissionsEditor::permissionChangeSummary([AdminPermission::AdminAccess], [AdminPermission::AdminAccess]))
        ->toBe(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.summary.none'));
});

it('ignores selected values outside the catalog and returns the catalog order', function (): void {
    expect(RolePermissionsEditor::selectedPermissions([
        'users' => [AdminPermission::AdminUsersView->value, 'admin.users.forget', 7],
        'access' => [AdminPermission::AdminAccess->value],
        'unknown' => 'admin.dashboard.view',
    ]))->toBe([AdminPermission::AdminAccess, AdminPermission::AdminUsersView])
        ->and(RolePermissionsEditor::selectedPermissions('admin.access'))->toBeEmpty();
});

it('groups the permissions of a role by area, and starts a new role empty', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
    $role = RoleFactory::new()->createOne();
    $role->givePermissionTo([AdminPermission::AdminPostsView->value, AdminPermission::AdminPostsViewAny->value]);

    $permissionsByArea = RolePermissionsEditor::permissionsByArea($role);
    $emptyState = RolePermissionsEditor::permissionsByArea(null);

    expect($permissionsByArea['posts'])->toBe([AdminPermission::AdminPostsViewAny->value, AdminPermission::AdminPostsView->value])
        ->and(array_merge(...array_values($permissionsByArea)))->toHaveCount(2)
        ->and(array_filter($emptyState))->toBeEmpty()
        ->and(array_keys($emptyState))->toBe(array_keys($permissionsByArea));
});

it('marks only the sensitive permissions in their checkbox', function (AdminPermission $adminPermission): void {
    $description = editorPermissionDescriptions()[$adminPermission->value];
    $sensitiveMark = e(TranslatedText::of('admin/roles.form.sensitive'));

    expect($description)->toContain(e($adminPermission->description()))
        ->and(str_contains($description, $sensitiveMark))->toBe($adminPermission->isSensitive());
})->with(AdminPermission::cases());

it('summarizes how many permissions are selected and which areas they cover', function (): void {
    expect(RolePermissionsEditor::permissionSelectionOverview([AdminPermission::AdminUsersViewAny, AdminPermission::AdminUsersView, AdminPermission::AdminPostsViewAny]))
        ->toBe('3 permissions. Areas: '.TranslatedText::of('admin/roles.catalog.areas.users').', '.TranslatedText::of('admin/roles.catalog.areas.posts'))
        ->and(RolePermissionsEditor::permissionSelectionOverview([]))->toBe('No permissions. '.TranslatedText::of('admin/roles.summary.no_areas'));
});

it('lists the sensitive permissions of the selection, or says there are none', function (): void {
    expect(RolePermissionsEditor::sensitivePermissionsText([AdminPermission::AdminUsersViewAny, AdminPermission::AdminUsersRoles, AdminPermission::AdminRolesDelete]))
        ->toBe(AdminPermission::AdminUsersRoles->label().', '.AdminPermission::AdminRolesDelete->label())
        ->and(RolePermissionsEditor::sensitivePermissionsText([AdminPermission::AdminUsersViewAny]))->toBe(TranslatedText::of('admin/roles.summary.no_sensitive'));
});

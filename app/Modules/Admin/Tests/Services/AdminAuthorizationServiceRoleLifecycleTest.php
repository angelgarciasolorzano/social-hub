<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use Illuminate\Support\Facades\DB;

function roleLifecycleService(): AdminAuthorizationService
{
    return resolve(AdminAuthorizationService::class);
}

/**
 * Run the callback and return the failure of the `AdminAuthorizationException` it throws.
 */
function roleLifecycleFailureOf(Closure $callback): ?AdminAuthorizationFailure
{
    try {
        $callback();
    } catch (AdminAuthorizationException $adminAuthorizationException) {
        return $adminAuthorizationException->failure;
    }

    return null;
}

function roleLifecycleSystemRole(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', Role::GUARD_NAME)
        ->sole();
}

/**
 * The permission names of a role, in catalog order.
 *
 * @return list<string>
 */
function roleLifecyclePermissionNames(Role $role): array
{
    $permissionNames = $role->refresh()->permissions->pluck('name')->all();

    return array_values(array_filter(
        array_map(static fn (AdminPermission $adminPermission): string => $adminPermission->value, AdminPermission::cases()),
        static fn (string $permissionName): bool => in_array($permissionName, $permissionNames, true),
    ));
}

function roleLifecycleActivity(ActivityLogAdminEvent $activityLogAdminEvent): ActivityLog
{
    return ActivityLog::query()->where('event', $activityLogAdminEvent->value)->sole();
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('creates a custom role with its texts, its permissions and an audit without texts', function (): void {
    $user = createAdminCommandActor();

    $role = roleLifecycleService()->createRole(
        '  Soporte nivel 1  ',
        '  Atiende a los usuarios.  ',
        [AdminPermission::AdminUsersView, AdminPermission::AdminUsersViewAny],
        $user,
    );

    $activityLog = roleLifecycleActivity(ActivityLogAdminEvent::RoleCreated);

    expect($role->name)->toBe('custom-soporte-nivel-1')
        ->and($role->guard_name)->toBe(Role::GUARD_NAME)
        ->and($role->refresh()->isSystem())->toBeFalse()
        ->and($role->label)->toBe('Soporte nivel 1')
        ->and($role->description)->toBe('Atiende a los usuarios.')
        ->and(roleLifecyclePermissionNames($role))->toBe([
            AdminPermission::AdminUsersViewAny->value,
            AdminPermission::AdminUsersView->value,
        ])
        ->and($activityLog->subject_id)->toBe($role->id)
        ->and($activityLog->causer_id)->toBe($user->id)
        ->and($activityLog->properties?->all())->toBe([
            'role' => 'custom-soporte-nivel-1',
            'permissions' => [AdminPermission::AdminUsersViewAny->value, AdminPermission::AdminUsersView->value],
        ]);
});

it('refuses a repeated label ignoring case, and suffixes the name of a different label with the same slug', function (): void {
    $user = createAdminCommandActor();
    roleLifecycleService()->createRole('Soporte nivel 1', null, [], $user);

    $failure = roleLifecycleFailureOf(
        static fn (): Role => roleLifecycleService()->createRole('SOPORTE NIVEL 1', null, [], $user),
    );
    $sameSlugRole = roleLifecycleService()->createRole('Soporte: nivel 1', null, [], $user);

    expect($failure)->toBe(AdminAuthorizationFailure::RoleLabelTaken)
        ->and($sameSlugRole->name)->toBe('custom-soporte-nivel-1-2')
        ->and(Role::query()->custom()->count())->toBe(2)
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleCreated->value)->count())->toBe(2);
});

it('refuses a selection that lacks a requirement and creates nothing', function (): void {
    $failure = roleLifecycleFailureOf(
        static fn (): Role => roleLifecycleService()->createRole('Auditor', null, [AdminPermission::AdminActivityView], createAdminCommandActor()),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::PermissionDependencyMissing)
        ->and(Role::query()->custom()->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('rejects a blank or too long text', function (string $label, ?string $description): void {
    expect(static fn (): Role => roleLifecycleService()->createRole($label, $description, [], createAdminCommandActor()))
        ->toThrow(InvalidArgumentException::class)
        ->and(Role::query()->custom()
            ->exists())
        ->toBeFalse();
})->with([
    'blank label' => ['   ', null],
    'long label' => [fn (): string => str_repeat('a', Role::LABEL_MAX_LENGTH + 1), null],
    'long description' => ['Auditor', fn (): string => str_repeat('a', Role::DESCRIPTION_MAX_LENGTH + 1)],
]);

it('updates the texts of a custom role, keeps its name and records only the changed attribute names', function (): void {
    $user = createAdminCommandActor();
    $role = roleLifecycleService()->createRole('Auditor', 'Revisa actividad.', [], $user);

    $changed = roleLifecycleService()->updateRoleDetails($role, 'Auditor externo', 'Revisa actividad.', $user);
    $unchanged = roleLifecycleService()->updateRoleDetails($role, ' Auditor externo ', 'Revisa actividad.', $user);
    $role->refresh();

    expect([$changed, $unchanged])->toBe([true, false])
        ->and($role->name)->toBe('custom-auditor')
        ->and($role->label)->toBe('Auditor externo')
        ->and(roleLifecycleActivity(ActivityLogAdminEvent::RoleUpdated)->properties?->all())->toBe([
            'role' => 'custom-auditor',
            'changed_attributes' => ['label'],
        ]);
});

it('lets a custom role change the case of its own label but not take another one', function (): void {
    $user = createAdminCommandActor();
    $auditorRole = roleLifecycleService()->createRole('Auditor', null, [], $user);
    roleLifecycleService()->createRole('Soporte', null, [], $user);

    $ownLabel = roleLifecycleService()->updateRoleDetails($auditorRole, 'AUDITOR', null, $user);
    $failure = roleLifecycleFailureOf(
        static fn (): bool => roleLifecycleService()->updateRoleDetails($auditorRole, 'soporte', null, $user),
    );

    expect($ownLabel)->toBeTrue()
        ->and($failure)->toBe(AdminAuthorizationFailure::RoleLabelTaken)
        ->and($auditorRole->refresh()->label)->toBe('AUDITOR');
});

it('refuses to rename or delete a system role', function (): void {
    $user = createAdminCommandActor();
    $systemRole = roleLifecycleSystemRole(AdminRole::AdminPostsReader);

    $renameFailure = roleLifecycleFailureOf(
        static fn (): bool => roleLifecycleService()->updateRoleDetails($systemRole, 'Lectores', null, $user),
    );
    $deleteFailure = roleLifecycleFailureOf(
        static fn () => roleLifecycleService()->deleteRole($systemRole, $user),
    );

    expect($renameFailure)->toBe(AdminAuthorizationFailure::SystemRoleProtected)
        ->and($deleteFailure)->toBe(AdminAuthorizationFailure::SystemRoleProtected)
        ->and($systemRole->refresh()->label)->toBeNull()
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('duplicates a system role as a custom role with its description and permissions', function (): void {
    $user = createAdminCommandActor();
    $sourceRole = roleLifecycleSystemRole(AdminRole::AdminUsersModerator);

    $role = roleLifecycleService()->duplicateRole($sourceRole, 'Moderación (copia)', $user);

    expect($role->name)->toBe('custom-moderacion-copia')
        ->and($role->refresh()->isSystem())->toBeFalse()
        ->and($role->description)->toBe($sourceRole->displayDescription())
        ->and(roleLifecyclePermissionNames($role))->toBe(roleLifecyclePermissionNames($sourceRole))
        ->and(roleLifecycleActivity(ActivityLogAdminEvent::RoleDuplicated)->properties?->all())->toBe([
            'role' => 'custom-moderacion-copia',
            'source_role_id' => $sourceRole->id,
        ]);
});

it('refuses to duplicate with a taken label', function (): void {
    $user = createAdminCommandActor();
    $sourceRole = roleLifecycleService()->createRole('Auditor', null, [], $user);

    $failure = roleLifecycleFailureOf(
        static fn (): Role => roleLifecycleService()->duplicateRole($sourceRole, 'auditor', $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::RoleLabelTaken)
        ->and(Role::query()->custom()->count())->toBe(1);
});

it('refuses to delete a custom role that still has users', function (): void {
    $user = createAdminCommandActor();
    $role = roleLifecycleService()->createRole('Auditor', null, [AdminPermission::AdminActivityViewAny], $user);
    createUser()->assignRole($role);

    $failure = roleLifecycleFailureOf(
        static fn () => roleLifecycleService()->deleteRole($role, $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::RoleHasUsers)
        ->and(Role::query()->whereKey($role->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleDeleted->value)->exists())->toBeFalse();
});

it('deletes a custom role without users together with its permission grants', function (): void {
    $user = createAdminCommandActor();
    $role = roleLifecycleService()->createRole('Auditor', null, [AdminPermission::AdminActivityViewAny], $user);
    $roleId = $role->id;

    roleLifecycleService()->deleteRole($role, $user);

    $activityLog = roleLifecycleActivity(ActivityLogAdminEvent::RoleDeleted);

    expect(Role::query()->whereKey($roleId)->exists())->toBeFalse()
        ->and(DB::table('role_has_permissions')->where('role_id', $roleId)->exists())->toBeFalse()
        ->and($activityLog->subject_id)->toBe($roleId)
        ->and($activityLog->causer_id)->toBe($user->id)
        ->and($activityLog->properties?->all())->toBe(['role' => 'custom-auditor']);
});

it('reports a role deleted meanwhile', function (): void {
    $user = createAdminCommandActor();
    $staleRole = RoleFactory::new()->createOne();
    Role::query()->whereKey($staleRole->id)->delete();

    $failure = roleLifecycleFailureOf(
        static fn (): bool => roleLifecycleService()->updateRoleDetails($staleRole, 'Auditor', null, $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::RoleNotInitialized);
});

it('never records the label or the description of a role', function (): void {
    $user = createAdminCommandActor();
    $role = roleLifecycleService()->createRole('Etiqueta secreta', 'Descripción secreta', [], $user);
    roleLifecycleService()->updateRoleDetails($role, 'Otra etiqueta', 'Otra descripción', $user);
    $copy = roleLifecycleService()->duplicateRole($role, 'Copia secreta', $user);
    roleLifecycleService()->deleteRole($copy, $user);

    $storedProperties = ActivityLog::query()->where('log_name', 'admin')->get()
        ->map(static fn (ActivityLog $activityLog): string => (string) json_encode($activityLog->properties))
        ->implode(' ');

    expect(ActivityLog::query()->where('log_name', 'admin')->count())->toBe(4)
        ->and($storedProperties)->not->toContain('Etiqueta secreta')
        ->not->toContain('Descripci')
        ->not->toContain('Otra etiqueta')
        ->not->toContain('Otra descripci')
        ->not->toContain('Copia secreta');
});

<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\RoleActivityChangeType;
use App\Modules\Admin\Filament\Concerns\DescribesRoleActivity;
use App\Support\Translation\TranslatedText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An activity of the given event with its properties stored as given (also malformed ones) and, if
 * any, its loaded subject.
 *
 * @param  array<mixed>  $properties
 */
function roleActivity(ActivityLogAdminEvent $activityLogAdminEvent, array $properties = [], ?Model $model = null): ActivityLog
{
    $activityLogFactory = ActivityLogFactory::new()->forEvent($activityLogAdminEvent)->state(['properties' => $properties]);

    if ($model instanceof Model) {
        $activityLogFactory = $activityLogFactory->performedOn($model);
    }

    return $activityLogFactory->createOne()->load('subject');
}

/**
 * The sentence the role history shows for an activity.
 *
 * @param  array<int, string>  $roleNamesById
 */
function describeRoleActivity(ActivityLog $activityLog, array $roleNamesById = []): string
{
    $describer = new class
    {
        use DescribesRoleActivity;

        /**
         * @param  array<int, string>  $roleNamesById
         */
        public function describe(ActivityLog $activityLog, array $roleNamesById): string
        {
            return self::roleActivityDetail($activityLog, $roleNamesById);
        }
    };

    return $describer->describe($activityLog, $roleNamesById);
}

/**
 * A text of the role history's details in the active language.
 *
 * @param  array<string, string|int>  $replace
 */
function roleHistoryDetail(string $detailKey, array $replace = []): string
{
    return TranslatedText::of('admin/roles.relations.history.detail.'.$detailKey, $replace);
}

it('classifies each role event in one kind of change', function (ActivityLogAdminEvent $activityLogAdminEvent, RoleActivityChangeType $roleActivityChangeType): void {
    expect(RoleActivityChangeType::forEvent($activityLogAdminEvent->value))->toBe($roleActivityChangeType);
})->with([
    [ActivityLogAdminEvent::RolePermissionGranted, RoleActivityChangeType::Permissions],
    [ActivityLogAdminEvent::RolePermissionRevoked, RoleActivityChangeType::Permissions],
    [ActivityLogAdminEvent::RoleGranted, RoleActivityChangeType::Users],
    [ActivityLogAdminEvent::RoleRevoked, RoleActivityChangeType::Users],
    [ActivityLogAdminEvent::AdminBootstrapped, RoleActivityChangeType::Users],
    [ActivityLogAdminEvent::RoleCreated, RoleActivityChangeType::Lifecycle],
    [ActivityLogAdminEvent::RoleUpdated, RoleActivityChangeType::Lifecycle],
    [ActivityLogAdminEvent::RoleDuplicated, RoleActivityChangeType::Lifecycle],
    [ActivityLogAdminEvent::RoleDeleted, RoleActivityChangeType::Lifecycle],
]);

it('does not classify events that are not role changes', function (?string $event): void {
    expect(RoleActivityChangeType::forEvent($event))->toBeNull();
})->with([
    ActivityLogAdminEvent::InvitationIssued->value,
    ActivityLogContentEvent::PostCreated->value,
    'admin.not_cataloged',
    null,
]);

it('puts each event in a single kind of change', function (): void {
    $events = [];

    foreach (RoleActivityChangeType::cases() as $roleActivityChangeType) {
        foreach ($roleActivityChangeType->events() as $activityLogAdminEvent) {
            $events[] = $activityLogAdminEvent->value;
        }
    }

    expect($events)->toHaveSameSize(array_unique($events));
});

it('names the permission added to or removed from the role', function (ActivityLogAdminEvent $activityLogAdminEvent, string $detailKey): void {
    $activityLog = roleActivity($activityLogAdminEvent, ['role' => 'custom-soporte', 'permission' => AdminPermission::AdminUsersView->value]);

    expect(describeRoleActivity($activityLog))
        ->toBe(roleHistoryDetail($detailKey, ['permission' => AdminPermission::AdminUsersView->label()]));
})->with([
    [ActivityLogAdminEvent::RolePermissionGranted, 'permission_granted'],
    [ActivityLogAdminEvent::RolePermissionRevoked, 'permission_revoked'],
]);

it('names the user who received or lost the role', function (ActivityLogAdminEvent $activityLogAdminEvent, string $detailKey): void {
    $user = createUser();
    $activityLog = roleActivity($activityLogAdminEvent, ['role' => 'custom-soporte', 'roles' => ['custom-soporte']], $user);

    expect(describeRoleActivity($activityLog))->toBe(roleHistoryDetail($detailKey, ['user' => $user->name]));
})->with([
    [ActivityLogAdminEvent::RoleGranted, 'role_granted'],
    [ActivityLogAdminEvent::RoleRevoked, 'role_revoked'],
    [ActivityLogAdminEvent::AdminBootstrapped, 'bootstrapped'],
]);

it('describes a deleted user by its id', function (): void {
    $user = createUser();
    $activityLog = roleActivity(ActivityLogAdminEvent::RoleGranted, ['role' => 'custom-soporte'], $user);
    $userId = $user->id;
    $user->delete();

    expect(describeRoleActivity($activityLog->load('subject')))->toBe('Assigned the role to Deleted user #'.$userId);
});

it('counts the permissions the role was created with', function (int $permissionCount, string $expectedDetail): void {
    $permissions = array_slice(array_map(static fn (AdminPermission $adminPermission): string => $adminPermission->value, AdminPermission::cases()), 0, $permissionCount);

    expect(describeRoleActivity(roleActivity(ActivityLogAdminEvent::RoleCreated, ['role' => 'custom-soporte', 'permissions' => $permissions])))
        ->toBe($expectedDetail);
})->with([
    [0, 'Created the role without permissions'],
    [1, 'Created the role with 1 permission'],
    [3, 'Created the role with 3 permissions'],
]);

it('says which texts of the role were changed', function (mixed $changedAttributes, string $detailKey): void {
    expect(describeRoleActivity(roleActivity(ActivityLogAdminEvent::RoleUpdated, ['role' => 'custom-soporte', 'changed_attributes' => $changedAttributes])))
        ->toBe(roleHistoryDetail($detailKey));
})->with([
    [['label'], 'updated_label'],
    [['description'], 'updated_description'],
    [['label', 'description'], 'updated_label_and_description'],
    [[], 'updated'],
    ['label', 'updated'],
]);

it('names the role a duplicate was copied from, or its id once deleted', function (): void {
    $role = RoleFactory::new()->createOne();
    $activityLog = roleActivity(ActivityLogAdminEvent::RoleDuplicated, ['role' => $role->name, 'source_role_id' => 7], $role);

    expect(describeRoleActivity($activityLog, [7 => 'Lector de actividad']))->toBe(roleHistoryDetail('duplicated', ['role' => 'Lector de actividad']))
        ->and(describeRoleActivity($activityLog))->toBe('Created as a copy of Role #7 (deleted)');
});

it('describes a deleted role', function (): void {
    expect(describeRoleActivity(roleActivity(ActivityLogAdminEvent::RoleDeleted, ['role' => 'custom-soporte'])))
        ->toBe(roleHistoryDetail('deleted'));
});

it('falls back to a generic text for incomplete or foreign activity', function (ActivityLogAdminEvent $activityLogAdminEvent, array $properties): void {
    expect(describeRoleActivity(roleActivity($activityLogAdminEvent, $properties)))->toBe(roleHistoryDetail('unknown'));
})->with([
    'permission outside the catalog' => [ActivityLogAdminEvent::RolePermissionGranted, ['permission' => 'admin.removed.permission']],
    'permission missing' => [ActivityLogAdminEvent::RolePermissionRevoked, []],
    'grant without subject' => [ActivityLogAdminEvent::RoleGranted, ['role' => 'custom-soporte']],
    'creation without permissions list' => [ActivityLogAdminEvent::RoleCreated, ['permissions' => 'admin.users.view']],
    'duplicate without source' => [ActivityLogAdminEvent::RoleDuplicated, []],
    'event that is not a role change' => [ActivityLogAdminEvent::InvitationIssued, ['role' => 'custom-soporte']],
]);

it('writes the details in Spanish', function (): void {
    app()->setLocale('es');

    $activityLog = roleActivity(ActivityLogAdminEvent::RolePermissionGranted, ['permission' => AdminPermission::AdminUsersView->value]);

    expect(describeRoleActivity($activityLog))->toBe('Agregó el permiso '.AdminPermission::AdminUsersView->label())
        ->and(describeRoleActivity(roleActivity(ActivityLogAdminEvent::RoleCreated, ['permissions' => ['admin.users.viewAny', 'admin.users.view']])))
        ->toBe('Creó el rol con 2 permisos');
});

it('runs no queries while describing an activity', function (): void {
    $activityLogs = [
        roleActivity(ActivityLogAdminEvent::RoleGranted, ['role' => 'custom-soporte'], createUser()),
        roleActivity(ActivityLogAdminEvent::RolePermissionGranted, ['permission' => AdminPermission::AdminUsersView->value]),
        roleActivity(ActivityLogAdminEvent::RoleDuplicated, ['source_role_id' => 7]),
    ];

    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach ($activityLogs as $activityLog) {
        describeRoleActivity($activityLog, [7 => 'Lector de actividad']);
    }

    $queryCount = \count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBe(0);
});

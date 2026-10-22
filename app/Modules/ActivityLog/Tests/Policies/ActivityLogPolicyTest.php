<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Policies\ActivityLogPolicy;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * A user holding only the given Admin permissions, through a role made for the test.
 */
function createUserWithActivityLogPermissions(AdminPermission ...$adminPermissions): User
{
    $role = Role::findOrCreate('activity-log-policy-test', 'admin');
    $role->givePermissionTo(array_map(fn (AdminPermission $adminPermission): string => $adminPermission->value, $adminPermissions));

    $user = createUser();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('resolves ActivityLogPolicy from the model attribute', function (): void {
    expect(Gate::getPolicyFor(ActivityLog::class))->toBeInstanceOf(ActivityLogPolicy::class);
});

it('allows listing activity only with admin.activity.viewAny', function (): void {
    $user = createUserWithActivityLogPermissions(AdminPermission::AdminActivityViewAny);
    $activityLog = ActivityLogFactory::new()->createOne();

    expect(Gate::forUser($user)->allows('viewAny', ActivityLog::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $activityLog))->toBeFalse();
});

it('allows opening an activity only with admin.activity.view', function (): void {
    $user = createUserWithActivityLogPermissions(AdminPermission::AdminActivityView);
    $activityLog = ActivityLogFactory::new()->createOne();

    expect(Gate::forUser($user)->allows('view', $activityLog))->toBeTrue()
        ->and(Gate::forUser($user)->allows('viewAny', ActivityLog::class))->toBeFalse();
});

it('does not grant activity access through admin.access or other sections', function (): void {
    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value, AdminRole::AdminPostsReader->value);

    $activityLog = ActivityLogFactory::new()->createOne();

    expect(Gate::forUser($user)->allows('viewAny', ActivityLog::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view', $activityLog))->toBeFalse();
});

it('denies every change even to an activity reader', function (string $ability): void {
    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminActivityReader->value);

    $activityLog = ActivityLogFactory::new()->createOne();

    $target = in_array($ability, ['create', 'deleteAny'], true) ? ActivityLog::class : $activityLog;

    expect(Gate::forUser($user)->allows($ability, $target))->toBeFalse();
})->with(['create', 'update', 'delete', 'deleteAny', 'restore', 'forceDelete']);

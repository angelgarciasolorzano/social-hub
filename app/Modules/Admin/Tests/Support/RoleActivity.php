<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Support;

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Models\Role;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records role activity as `AdminAuthorizationService` would, for tests of the role history and dates.
 */
final class RoleActivity
{
    /**
     * A grant of the role to a user, by the administrator or by a new user; at the given time or now.
     */
    public static function grant(Role $role, User $holder, ?User $administrator = null, ?string $at = null): ActivityLog
    {
        return self::record(ActivityLogAdminEvent::RoleGranted, $holder, ['role' => $role->name], $administrator ?? createUser(), $at);
    }

    /**
     * A change of the role itself (created, updated, a permission…), by the causer or by a new user.
     *
     * @param  array<string, mixed>  $properties  added to the role's name
     */
    public static function change(Role $role, ActivityLogAdminEvent $activityLogAdminEvent, ?User $user = null, ?string $at = null, array $properties = []): ActivityLog
    {
        return self::record($activityLogAdminEvent, $role, ['role' => $role->name, ...$properties], $user ?? createUser(), $at);
    }

    /**
     * Any admin activity; without a causer it is anonymous, without a date it is recorded now.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(ActivityLogAdminEvent $activityLogAdminEvent, ?Model $model = null, array $properties = [], ?User $user = null, ?string $at = null): ActivityLog
    {
        $activityLogFactory = ActivityLogFactory::new()->forEvent($activityLogAdminEvent);

        if ($model instanceof Model) {
            $activityLogFactory = $activityLogFactory->performedOn($model);
        }

        if ($user instanceof User) {
            $activityLogFactory = $activityLogFactory->causedBy($user);
        }

        $attributes = ['properties' => $properties];

        if ($at !== null) {
            $attributes['created_at'] = CarbonImmutable::parse($at);
        }

        return $activityLogFactory->createOne($attributes);
    }
}

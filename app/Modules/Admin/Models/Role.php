<?php

declare(strict_types=1);

namespace App\Modules\Admin\Models;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Policies\RolePolicy;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * An administrator role of Spatie Permission with its presentation: system roles come from the
 * `AdminRole` catalog and read their texts from `lang/`, custom roles keep the ones they were saved with.
 *
 * @property bool $is_system
 * @property string|null $label
 * @property string|null $description
 *
 * @mixin IdeHelperRole
 */
#[UsePolicy(RolePolicy::class)]
#[UseFactory(RoleFactory::class)]
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const string GUARD_NAME = 'admin';

    public const string CUSTOM_NAME_PREFIX = 'custom-';

    public const int LABEL_MAX_LENGTH = 80;

    public const int DESCRIPTION_MAX_LENGTH = 255;

    /**
     * A role created without saying otherwise is custom, also before it is read back from the database.
     *
     * @var array<string, mixed>
     */
    #[Override]
    protected $attributes = [
        'is_system' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Limit the query to the roles of the `admin` guard.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function admin(Builder $builder): void
    {
        $builder->where('guard_name', self::GUARD_NAME);
    }

    /**
     * Limit the query to the roles of the catalog.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function system(Builder $builder): void
    {
        $builder->where('is_system', true);
    }

    /**
     * Limit the query to the roles created from the panel.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function custom(Builder $builder): void
    {
        $builder->where('is_system', false);
    }

    /**
     * Limit a roles query to the roles of the `admin` guard that grant the permission. Takes any builder
     * because Spatie types the `roles` relation as `Model`, so `whereHas('roles')` cannot use the scopes.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
     * @return Builder<TModel>
     */
    public static function grantingPermission(Builder $builder, AdminPermission $adminPermission): Builder
    {
        $builder->getQuery()->where('roles.guard_name', self::GUARD_NAME);

        return $builder->whereHas('permissions', static function (Builder $builder) use ($adminPermission): Builder {
            $builder->getQuery()
                ->where('permissions.guard_name', self::GUARD_NAME)
                ->where('permissions.name', $adminPermission->value);

            return $builder;
        });
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /**
     * Whether nobody may change this role: the `admin` role only grants panel access.
     */
    public function isLocked(): bool
    {
        return $this->guard_name === self::GUARD_NAME && $this->name === AdminRole::Admin->value;
    }

    /**
     * The catalog case of a system role, or null for a custom role.
     */
    public function adminRole(): ?AdminRole
    {
        return $this->isSystem() ? AdminRole::tryFrom($this->name) : null;
    }

    /**
     * The name shown in the panel: the catalog's translated name, the saved label, or the stored name.
     */
    public function displayName(): string
    {
        return $this->adminRole()?->label() ?? $this->label ?? $this->name;
    }

    /**
     * The description shown in the panel: the catalog's translated one or the saved one.
     */
    public function displayDescription(): ?string
    {
        return $this->adminRole()?->description() ?? $this->description;
    }

    /**
     * The activity of this role: its own changes, its grants and revocations to users and the
     * bootstrap that gave it, counting the last two only after the last deleted role of the same name.
     *
     * @return Builder<ActivityLog>
     */
    public function activityLogQuery(): Builder
    {
        return ActivityLog::query()->where(fn (Builder $builder): Builder => $builder
            ->where(fn (Builder $builder): Builder => $builder
                ->where('subject_type', $this->getMorphClass())
                ->where('subject_id', $this->getKey()))
            ->orWhere(fn (Builder $builder): Builder => $builder
                ->where('id', '>', $this->lastDeletionOfNameQuery())
                ->where(fn (Builder $builder): Builder => $builder
                    ->where(fn (Builder $builder): Builder => $builder
                        ->whereIn('event', [ActivityLogAdminEvent::RoleGranted->value, ActivityLogAdminEvent::RoleRevoked->value])
                        ->whereJsonContains('properties->role', $this->name))
                    ->orWhere(fn (Builder $builder): Builder => $builder
                        ->where('event', ActivityLogAdminEvent::AdminBootstrapped->value)
                        ->whereJsonContains('properties->roles', $this->name)))));
    }

    /**
     * The grants of this role to users, only after the last deleted role of the same name.
     *
     * @return Builder<ActivityLog>
     */
    public function grantActivityQuery(): Builder
    {
        $builder = ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleGranted->value);
        $builder->whereJsonContains('properties->role', $this->name);

        return $builder->where('id', '>', $this->lastDeletionOfNameQuery());
    }

    /**
     * The latest grant of this role to each of the given users, with its causer, keyed by user id; a
     * user without a recorded grant is left out.
     *
     * @param  array<int>  $userIds
     * @return array<int, ActivityLog>
     */
    public function latestGrantsFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $grants = $this->grantActivityQuery()
            ->where('subject_type', User::MORPH_NAME)
            ->whereIn('subject_id', array_values(array_unique($userIds)))
            ->with('causer')
            ->orderByDesc('id')
            ->get();

        $latestGrants = [];

        foreach ($grants as $grant) {
            if ($grant->subject_id !== null && ! isset($latestGrants[$grant->subject_id])) {
                $latestGrants[$grant->subject_id] = $grant;
            }
        }

        return $latestGrants;
    }

    /**
     * The date of the latest grant of this role to the user of the outer `users` query, for selecting,
     * sorting and filtering users by it.
     *
     * @return Builder<ActivityLog>
     */
    public function latestGrantDateSubquery(): Builder
    {
        $builder = $this->grantActivityQuery()->where('subject_type', User::MORPH_NAME);
        $builder->whereColumn('subject_id', 'users.id')->selectRaw('max(created_at)');

        return $builder;
    }

    /**
     * The id of the last deletion of a role with this stored name, or 0, as a subquery.
     *
     * @return Builder<ActivityLog>
     */
    private function lastDeletionOfNameQuery(): Builder
    {
        $builder = ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleDeleted->value);
        $builder->whereJsonContains('properties->role', $this->name)->selectRaw('coalesce(max(id), 0)');

        return $builder;
    }

    /**
     * The date of the latest change of the role of the outer `roles` query (its creation, details,
     * duplicate or permissions; never its grants to users), for selecting, sorting and filtering roles by
     * it. Null when the role has no recorded change.
     *
     * @return Builder<ActivityLog>
     */
    public static function lastChangeDateSubquery(): Builder
    {
        $builder = self::changeActivityQuery();
        $builder->whereColumn('subject_id', 'roles.id')->selectRaw('max(created_at)');

        return $builder;
    }

    /**
     * The latest change of each of the given roles, with its causer, keyed by role id; a role without a
     * recorded change is left out. The latest is the one with the newest date, as the shown date reads
     * it, and the highest id among changes recorded at the same time.
     *
     * @param  array<int|string>  $roleIds
     * @return array<int, ActivityLog>
     */
    public static function latestChangesFor(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $changes = self::changeActivityQuery()
            ->whereIn('subject_id', array_values(array_unique($roleIds)))
            ->with('causer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return self::firstActivityPerSubject($changes);
    }

    /**
     * The creation of each of the given roles, with its causer, keyed by role id: its `admin.role_created`
     * or, for a copy, its `admin.role_duplicated`. A role created without a record (a seeded system role)
     * is left out.
     *
     * @param  array<int|string>  $roleIds
     * @return array<int, ActivityLog>
     */
    public static function creationsFor(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $creations = self::changeActivityQuery()
            ->whereIn('event', [ActivityLogAdminEvent::RoleCreated->value, ActivityLogAdminEvent::RoleDuplicated->value])
            ->whereIn('subject_id', array_values(array_unique($roleIds)))
            ->with('causer')
            ->orderByDesc('id')
            ->get();

        return self::firstActivityPerSubject($creations);
    }

    /**
     * The activity whose subject is a role: the changes of the role itself.
     *
     * @return Builder<ActivityLog>
     */
    private static function changeActivityQuery(): Builder
    {
        return ActivityLog::query()->where('subject_type', new self()->getMorphClass());
    }

    /**
     * The first activity of each subject, keyed by subject id.
     *
     * @param  iterable<ActivityLog>  $activityLogs
     * @return array<int, ActivityLog>
     */
    private static function firstActivityPerSubject(iterable $activityLogs): array
    {
        $firstActivities = [];

        foreach ($activityLogs as $activityLog) {
            if ($activityLog->subject_id !== null && ! isset($firstActivities[$activityLog->subject_id])) {
                $firstActivities[$activityLog->subject_id] = $activityLog;
            }
        }

        return $firstActivities;
    }

    /**
     * The roles as the panel lists them: system roles in catalog order, then custom roles by shown name.
     *
     * @param  iterable<Role>  $roles
     * @return list<Role>
     */
    public static function inDisplayOrder(iterable $roles): array
    {
        $catalogPositions = array_flip(array_map(static fn (AdminRole $adminRole): string => $adminRole->value, AdminRole::cases()));
        $systemRoles = [];
        $customRoles = [];

        foreach ($roles as $role) {
            $adminRole = $role->adminRole();

            if ($adminRole instanceof AdminRole) {
                $systemRoles[$catalogPositions[$adminRole->value]] = $role;
            } else {
                $customRoles[] = $role;
            }
        }

        ksort($systemRoles);
        usort($customRoles, static fn (Role $first, Role $second): int => strnatcasecmp($first->displayName(), $second->displayName()));

        return [...array_values($systemRoles), ...$customRoles];
    }
}

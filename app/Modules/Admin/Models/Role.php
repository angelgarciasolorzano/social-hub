<?php

declare(strict_types=1);

namespace App\Modules\Admin\Models;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Policies\RolePolicy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        $lastDeletionOfName = ActivityLog::query()
            ->selectRaw('coalesce(max(id), 0)')
            ->where('event', ActivityLogAdminEvent::RoleDeleted->value)
            ->whereJsonContains('properties->role', $this->name);

        return ActivityLog::query()->where(fn (Builder $builder): Builder => $builder
            ->where(fn (Builder $builder): Builder => $builder
                ->where('subject_type', $this->getMorphClass())
                ->where('subject_id', $this->getKey()))
            ->orWhere(fn (Builder $builder): Builder => $builder
                ->where('id', '>', $lastDeletionOfName)
                ->where(fn (Builder $builder): Builder => $builder
                    ->where(fn (Builder $builder): Builder => $builder
                        ->whereIn('event', [ActivityLogAdminEvent::RoleGranted->value, ActivityLogAdminEvent::RoleRevoked->value])
                        ->whereJsonContains('properties->role', $this->name))
                    ->orWhere(fn (Builder $builder): Builder => $builder
                        ->where('event', ActivityLogAdminEvent::AdminBootstrapped->value)
                        ->whereJsonContains('properties->roles', $this->name)))));
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

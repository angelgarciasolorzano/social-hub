<?php

declare(strict_types=1);

namespace App\Modules\Admin\Models;

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
}

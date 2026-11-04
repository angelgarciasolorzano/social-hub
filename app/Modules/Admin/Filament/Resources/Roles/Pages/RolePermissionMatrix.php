<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Override;
use Spatie\Permission\Models\Permission;

/**
 * Which permissions each cataloged role grants, as a read-only table of permissions (rows, grouped by
 * area) by roles (columns). Roles are edited from their own detail page.
 */
class RolePermissionMatrix extends Page
{
    #[Override]
    protected static string $resource = RoleResource::class;

    #[Override]
    protected string $view = 'admin::filament.roles.permission-matrix';

    #[Override]
    public static function canAccess(array $parameters = []): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Role::class);
    }

    #[Override]
    public function getTitle(): string
    {
        return TranslatedText::of('admin/roles.matrix.title');
    }

    #[Override]
    public function getSubheading(): string
    {
        return TranslatedText::of('admin/roles.matrix.description');
    }

    /**
     * The cataloged roles that exist, in catalog order, with the permissions each grants. One query.
     *
     * @return list<array{label: string, description: string, permissions: array<int, string>}>
     */
    public function getMatrixRoles(): array
    {
        $permissionsByRole = [];

        foreach (Role::query()->where('guard_name', 'admin')->with('permissions')->get() as $role) {
            $permissionsByRole[$role->name] = $role->permissions
                ->map(static fn (Permission $permission): string => $permission->name)
                ->values()
                ->all();
        }

        $matrixRoles = [];

        foreach (AdminRole::cases() as $adminRole) {
            if (\array_key_exists($adminRole->value, $permissionsByRole)) {
                $matrixRoles[] = [
                    'label' => $adminRole->label(),
                    'description' => $adminRole->description(),
                    'permissions' => $permissionsByRole[$adminRole->value],
                ];
            }
        }

        return $matrixRoles;
    }

    /**
     * The cataloged permissions grouped by area, in catalog order.
     *
     * @return array<string, list<array{name: string, label: string, description: string}>>
     */
    public function getMatrixAreas(): array
    {
        $matrixAreas = [];

        foreach (AdminPermission::cases() as $adminPermission) {
            $matrixAreas[TranslatedText::of('admin/roles.catalog.areas.'.$adminPermission->area())][] = [
                'name' => $adminPermission->value,
                'label' => $adminPermission->label(),
                'description' => $adminPermission->description(),
            ];
        }

        return $matrixAreas;
    }
}

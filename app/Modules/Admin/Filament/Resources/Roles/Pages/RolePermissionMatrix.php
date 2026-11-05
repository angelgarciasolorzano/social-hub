<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminPermission;
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
 * Which permissions each role of the `admin` guard grants, as a read-only table of permissions (rows,
 * grouped by area) by roles (columns: system roles in catalog order, then custom roles by name). Roles
 * are edited from their own detail page.
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
     * The roles of the `admin` guard with the shown name, description and permissions of each, system
     * roles first in catalog order and then custom roles by name. One query for the roles and one for
     * their permissions, whatever the number of roles.
     *
     * @return list<array{label: string, description: string, permissions: array<int, string>}>
     */
    public function getMatrixRoles(): array
    {
        $matrixRoles = [];

        foreach (Role::inDisplayOrder(Role::query()->where('guard_name', Role::GUARD_NAME)->with('permissions')->get()) as $role) {
            $matrixRoles[] = [
                'label' => $role->displayName(),
                'description' => $role->displayDescription() ?? '',
                'permissions' => $role->permissions
                    ->map(static fn (Permission $permission): string => $permission->name)
                    ->values()
                    ->all(),
            ];
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

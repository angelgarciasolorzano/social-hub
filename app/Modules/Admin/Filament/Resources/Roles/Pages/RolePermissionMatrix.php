<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Override;
use Spatie\Permission\Models\Permission;

/**
 * Which permissions each role of the `admin` guard grants, as a read-only table of permissions (rows,
 * grouped by area) by roles (columns: system roles in catalog order, then custom roles by name). Roles
 * are edited from their own detail page.
 *
 * It also compares roles: with 2 to 4 roles chosen (kept in the URL, `?roles[]=…`) it shows only those,
 * and "only differences" (`only_differences=1`) hides the permissions every shown role agrees on. A
 * selection of another size or with unknown names is ignored with a notice.
 */
class RolePermissionMatrix extends Page
{
    public const int MIN_COMPARED_ROLES = 2;

    public const int MAX_COMPARED_ROLES = 4;

    /**
     * The stored names of the roles to compare, as the URL sent them.
     *
     * @var array<mixed>
     */
    #[Url]
    public array $roles = [];

    /**
     * Whether to hide the permissions every shown role agrees on.
     */
    #[Url(as: 'only_differences')]
    public bool $onlyDifferences = false;

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
     * The roles to compare and whether to show only the differences, both kept in the URL.
     */
    public function compareForm(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3])->schema([
                Select::make('roles')
                    ->label(TranslatedText::of('admin/roles.compare.roles'))
                    ->helperText(TranslatedText::of('admin/roles.compare.roles_helper', ['min' => self::MIN_COMPARED_ROLES, 'max' => self::MAX_COMPARED_ROLES]))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => $this->roleOptions())
                    ->maxItems(self::MAX_COMPARED_ROLES)
                    ->live()
                    ->columnSpan(['default' => 1, 'md' => 2]),
                Toggle::make('onlyDifferences')
                    ->label(TranslatedText::of('admin/roles.compare.only_differences'))
                    ->live(),
            ]),
        ]);
    }

    /**
     * Whether roles were chosen to compare but the choice cannot be used: fewer than 2, more than 4, or
     * a name that is not a role of the `admin` guard.
     */
    public function hasInvalidComparison(): bool
    {
        return $this->roles !== [] && $this->comparedRoleNames() === null;
    }

    /**
     * The roles of the `admin` guard with the stored and shown name, description and permissions of
     * each, system roles first in catalog order and then custom roles by name; only the compared roles
     * when the comparison is valid. One query for the roles and one for their permissions, whatever the
     * number of roles.
     *
     * @return list<array{name: string, label: string, description: string, permissions: array<int, string>}>
     */
    public function getMatrixRoles(): array
    {
        $comparedRoleNames = $this->comparedRoleNames();
        $builder = $this->adminRoleQuery()->with('permissions');

        if ($comparedRoleNames !== null) {
            $builder->whereIn('name', $comparedRoleNames);
        }

        $matrixRoles = [];

        foreach (Role::inDisplayOrder($builder->get()) as $role) {
            $matrixRoles[] = [
                'name' => $role->name,
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
     * The cataloged permissions grouped by area, in catalog order, each saying whether it is sensitive.
     * With "only differences", only the permissions some of the given roles grant and others do not, and
     * only the areas left with one.
     *
     * @param  list<array{name: string, label: string, description: string, permissions: array<int, string>}>|null  $matrixRoles
     * @return array<string, list<array{name: string, label: string, description: string, sensitive: bool}>>
     */
    public function getMatrixAreas(?array $matrixRoles = null): array
    {
        $comparedRoles = $this->onlyDifferences ? ($matrixRoles ?? $this->getMatrixRoles()) : [];
        $matrixAreas = [];

        foreach (AdminPermission::cases() as $adminPermission) {
            if ($this->onlyDifferences && ! $this->differsAmong($adminPermission, $comparedRoles)) {
                continue;
            }

            $matrixAreas[TranslatedText::of('admin/roles.catalog.areas.'.$adminPermission->area())][] = [
                'name' => $adminPermission->value,
                'label' => $adminPermission->label(),
                'description' => $adminPermission->description(),
                'sensitive' => $adminPermission->isSensitive(),
            ];
        }

        return $matrixAreas;
    }

    /**
     * Whether some of the roles grant the permission and others do not.
     *
     * @param  list<array{name: string, label: string, description: string, permissions: array<int, string>}>  $matrixRoles
     */
    private function differsAmong(AdminPermission $adminPermission, array $matrixRoles): bool
    {
        $grantingRoleCount = \count(array_filter(
            $matrixRoles,
            static fn (array $matrixRole): bool => \in_array($adminPermission->value, $matrixRole['permissions'], true),
        ));

        return $grantingRoleCount > 0 && $grantingRoleCount < \count($matrixRoles);
    }

    /**
     * The names of the roles to compare, or null when there is no valid comparison.
     *
     * @return list<string>|null
     */
    private function comparedRoleNames(): ?array
    {
        $roleNames = array_values(array_unique(array_filter($this->roles, \is_string(...))));

        if (\count($roleNames) !== \count($this->roles)
            || \count($roleNames) < self::MIN_COMPARED_ROLES
            || \count($roleNames) > self::MAX_COMPARED_ROLES) {
            return null;
        }

        return $this->adminRoleQuery()->whereIn('name', $roleNames)->count() === \count($roleNames) ? $roleNames : null;
    }

    /**
     * The roles of the `admin` guard to choose from, in display order, by stored name.
     *
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        $roleOptions = [];

        foreach (Role::inDisplayOrder($this->adminRoleQuery()->get()) as $role) {
            $roleOptions[$role->name] = $role->displayName();
        }

        return $roleOptions;
    }

    /**
     * @return Builder<Role>
     */
    private function adminRoleQuery(): Builder
    {
        return Role::query()->where('guard_name', Role::GUARD_NAME);
    }
}

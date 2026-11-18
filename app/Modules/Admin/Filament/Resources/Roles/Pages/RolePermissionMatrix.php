<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use Override;
use Spatie\Permission\Models\Permission;

/**
 * Read-only table of the permissions (rows, by area) each role of the `admin` guard grants (columns).
 * Compares 2 to 4 roles kept in the URL (`?roles[]=…`), optionally showing only the permissions they
 * disagree on (`only_differences=1`); any other selection is ignored with a notice.
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
        return RoleResource::canViewAny();
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
     * Whether roles were chosen but cannot be compared: too few, too many or an unknown name.
     */
    public function hasInvalidComparison(): bool
    {
        return $this->roles !== [] && $this->comparedRoleNames() === null;
    }

    /**
     * The roles of the `admin` guard in display order (only the compared ones when the comparison is
     * valid), with their permissions. Two queries whatever the number of roles.
     *
     * @return list<array{name: string, label: string, description: string, permissions: array<int, string>}>
     */
    public function getMatrixRoles(): array
    {
        $comparedRoleNames = $this->comparedRoleNames();
        $builder = Role::query()->admin()->with('permissions');

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
     * The cataloged permissions by area, in catalog order. With "only differences", only the ones the
     * given roles disagree on, and only the areas left with one.
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

        return Role::query()->admin()->whereIn('name', $roleNames)->count() === \count($roleNames) ? $roleNames : null;
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        $roleOptions = [];

        foreach (Role::inDisplayOrder(Role::query()->admin()->get()) as $role) {
            $roleOptions[$role->name] = $role->displayName();
        }

        return $roleOptions;
    }
}

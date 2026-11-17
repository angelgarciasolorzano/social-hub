<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Filament\Resources\Roles\Tables\PermissionHoldersTable;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Override;

/**
 * Who can do something: the roles of the `admin` guard that grant one cataloged permission and the
 * users who hold it through any of them, in a table filtered by status and panel MFA. The permission is kept in the URL (`?permission=<value>`), so
 * other screens link here; an unknown value leaves the selector empty. Read-only.
 */
class PermissionHolders extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * The value of the chosen permission, as the URL or the selector sent it.
     */
    #[Url]
    public ?string $permission = null;

    /**
     * The holder summary of this request, so the metrics and the role shares share one query.
     *
     * @var array{holders: int, suspended: int, withoutMfa: int, unverified: int, withoutPanelAccess: int}|null
     */
    private ?array $holderSummary = null;

    #[Override]
    protected static string $resource = RoleResource::class;

    #[Override]
    protected string $view = 'admin::filament.roles.permission-holders';

    /**
     * Seeing who holds a permission lists roles and users, so it needs to list both.
     */
    #[Override]
    public static function canAccess(array $parameters = []): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Role::class)
            && Gate::forUser($authenticatedUser)->allows('viewAny', User::class);
    }

    /**
     * The URL of this page with a permission chosen, or null when the administrator may not open it.
     */
    public static function urlFor(AdminPermission $adminPermission): ?string
    {
        return self::canAccess()
            ? RoleResource::getUrl('access', ['permission' => $adminPermission->value])
            : null;
    }

    public function mount(): void
    {
        if (! AdminPermission::tryFrom((string) $this->permission) instanceof AdminPermission) {
            $this->permission = null;
        }
    }

    #[Override]
    public function getTitle(): string
    {
        return TranslatedText::of('admin/roles.access.title');
    }

    #[Override]
    public function getSubheading(): string
    {
        return TranslatedText::of('admin/roles.access.description');
    }

    /**
     * The cataloged permission to look up, kept in the URL, grouped by area.
     */
    public function permissionForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('permission')
                ->label(TranslatedText::of('admin/roles.access.permission'))
                ->placeholder(TranslatedText::of('admin/roles.access.permission_placeholder'))
                ->options(fn (): array => $this->permissionOptions())
                ->searchable()
                ->live(),
        ]);
    }

    /**
     * Another permission was picked: show its holders from the first page, keeping the filters.
     */
    public function updatedPermission(): void
    {
        $this->holderSummary = null;
        $this->resetPage();
    }

    /**
     * The users who hold the chosen permission; see PermissionHoldersTable.
     */
    public function table(Table $table): Table
    {
        return PermissionHoldersTable::configure($table, $this->selectedPermission(...));
    }

    public function selectedPermission(): ?AdminPermission
    {
        return $this->permission === null ? null : AdminPermission::tryFrom($this->permission);
    }

    /**
     * The roles of the `admin` guard that grant the chosen permission, in display order, with their
     * number of users, the percentage of the holders they reach and a link to their detail when the
     * administrator may open it. One query, plus the holder summary.
     *
     * @return list<array{name: string, label: string, isSystem: bool, usersCount: int, share: int, url: string|null}>
     */
    public function getHolderRoles(): array
    {
        $adminPermission = $this->selectedPermission();

        if (! $adminPermission instanceof AdminPermission) {
            return [];
        }

        $roles = $this->grantingRoleQuery($adminPermission)->withCount('users')->get();
        $holders = $this->getHolderSummary()['holders'];
        $holderRoles = [];

        foreach (Role::inDisplayOrder($roles) as $role) {
            $usersCountAttribute = $role->getAttribute('users_count');
            $usersCount = is_numeric($usersCountAttribute) ? (int) $usersCountAttribute : 0;

            $holderRoles[] = [
                'name' => $role->name,
                'label' => $role->displayName(),
                'isSystem' => $role->isSystem(),
                'usersCount' => $usersCount,
                'share' => $holders > 0 ? (int) round($usersCount * 100 / $holders) : 0,
                'url' => RoleResource::canView($role) ? RoleResource::getUrl('view', ['record' => $role]) : null,
            ];
        }

        return $holderRoles;
    }

    /**
     * How many users hold the chosen permission, and how many of them cannot enter the panel today, by
     * each requirement of `User::canAccessPanel()` and the panel's MFA: suspended, without the panel MFA,
     * with an unverified email, or without `admin.access` through a role of the `admin` guard. Two
     * queries, the second skipped when the chosen permission is `admin.access` itself.
     *
     * @return array{holders: int, suspended: int, withoutMfa: int, unverified: int, withoutPanelAccess: int}
     */
    public function getHolderSummary(): array
    {
        $adminPermission = $this->selectedPermission();

        if (! $adminPermission instanceof AdminPermission) {
            return ['holders' => 0, 'suspended' => 0, 'withoutMfa' => 0, 'unverified' => 0, 'withoutPanelAccess' => 0];
        }

        if ($this->holderSummary !== null) {
            return $this->holderSummary;
        }

        $summary = self::holdersQuery($adminPermission)
            ->selectRaw(
                'count(*) as holders, '
                .'coalesce(sum(case when status = ? then 1 else 0 end), 0) as suspended, '
                .'coalesce(sum(case when app_authentication_secret is null then 1 else 0 end), 0) as without_mfa, '
                .'coalesce(sum(case when email_verified_at is null then 1 else 0 end), 0) as unverified',
                [AccountStatus::Suspended->value],
            )
            ->toBase()
            ->first();

        $summaryRow = \is_object($summary) ? get_object_vars($summary) : [];

        return $this->holderSummary = [
            'holders' => $this->summaryCount($summaryRow, 'holders'),
            'suspended' => $this->summaryCount($summaryRow, 'suspended'),
            'withoutMfa' => $this->summaryCount($summaryRow, 'without_mfa'),
            'unverified' => $this->summaryCount($summaryRow, 'unverified'),
            'withoutPanelAccess' => $adminPermission === AdminPermission::AdminAccess
                ? 0
                : self::holdersQuery($adminPermission)
                    ->whereDoesntHave('roles', self::grantingAdminRole(AdminPermission::AdminAccess))
                    ->count(),
        ];
    }

    /**
     * The permissions the chosen one needs and the ones that need it, each with its label and a link to
     * this page with it chosen.
     *
     * @return array{requires: list<array{label: string, url: string|null}>, dependents: list<array{label: string, url: string|null}>}
     */
    public function getRequirements(): array
    {
        $adminPermission = $this->selectedPermission();

        if (! $adminPermission instanceof AdminPermission) {
            return ['requires' => [], 'dependents' => []];
        }

        $linkTo = static fn (AdminPermission $relatedPermission): array => [
            'label' => $relatedPermission->label(),
            'url' => self::urlFor($relatedPermission),
        ];

        return [
            'requires' => array_map($linkTo, $adminPermission->requires()),
            'dependents' => array_map($linkTo, $adminPermission->dependents()),
        ];
    }

    /**
     * The users who hold the permission through any role of the `admin` guard, each once.
     *
     * @return Builder<User>
     */
    public static function holdersQuery(AdminPermission $adminPermission): Builder
    {
        return User::query()->whereHas('roles', self::grantingAdminRole($adminPermission));
    }

    /**
     * Keep the roles of the `admin` guard that grant the permission, for a `whereHas('roles')`.
     *
     * @return Closure(Builder<Model>): Builder<Model>
     */
    private static function grantingAdminRole(AdminPermission $adminPermission): Closure
    {
        return static function (Builder $roles) use ($adminPermission): Builder {
            $roles->getQuery()->where('roles.guard_name', Role::GUARD_NAME);

            return $roles->whereHas('permissions', self::grantsPermission($adminPermission));
        };
    }

    /**
     * The roles of the `admin` guard that grant the permission.
     *
     * @return Builder<Role>
     */
    private function grantingRoleQuery(AdminPermission $adminPermission): Builder
    {
        return Role::query()
            ->where('guard_name', Role::GUARD_NAME)
            ->whereHas('permissions', self::grantsPermission($adminPermission));
    }

    /**
     * Keep the permissions of the `admin` guard with the given value, for a `whereHas('permissions')`.
     *
     * @return Closure(Builder<Model>): Builder<Model>
     */
    public static function grantsPermission(AdminPermission $adminPermission): Closure
    {
        return static function (Builder $permissions) use ($adminPermission): Builder {
            $permissions->getQuery()
                ->where('permissions.guard_name', Role::GUARD_NAME)
                ->where('permissions.name', $adminPermission->value);

            return $permissions;
        };
    }

    /**
     * The cataloged permissions by translated area, in catalog order, by value.
     *
     * @return array<string, array<string, string>>
     */
    private function permissionOptions(): array
    {
        $permissionOptions = [];

        foreach (AdminPermission::cases() as $adminPermission) {
            $permissionOptions[TranslatedText::of('admin/roles.catalog.areas.'.$adminPermission->area())][$adminPermission->value] = $adminPermission->label();
        }

        return $permissionOptions;
    }

    /**
     * One count of the summary row, as a number.
     *
     * @param  array<mixed>  $summaryRow
     */
    private function summaryCount(array $summaryRow, string $column): int
    {
        $count = $summaryRow[$column] ?? null;

        return is_numeric($count) ? (int) $count : 0;
    }
}

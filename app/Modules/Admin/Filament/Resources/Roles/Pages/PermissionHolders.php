<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Concerns\ReadsAggregateCounts;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Filament\Resources\Roles\Tables\PermissionHoldersTable;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Override;

/**
 * Who can do something: the roles of the `admin` guard that grant one cataloged permission and the users
 * holding it through them. Read-only; the permission is kept in the URL (`?permission=<value>`).
 */
class PermissionHolders extends Page implements HasTable
{
    use InteractsWithTable;
    use ReadsAggregateCounts;

    /**
     * The chosen permission's value, as the URL or the selector sent it.
     */
    #[Url]
    public ?string $permission = null;

    /**
     * The holder summary, kept for the request so the metrics and the role shares share its queries.
     *
     * @var array{holders: int, suspended: int, withoutMfa: int, unverified: int, withoutPanelAccess: int}|null
     */
    private ?array $holderSummary = null;

    #[Override]
    protected static string $resource = RoleResource::class;

    #[Override]
    protected string $view = 'admin::filament.roles.permission-holders';

    /**
     * The page lists roles and users, so it needs to list both.
     */
    #[Override]
    public static function canAccess(array $parameters = []): bool
    {
        return RoleResource::canViewAny() && UserResource::canViewAny();
    }

    /**
     * This page with the permission chosen, or null when the administrator may not open it.
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

    public function table(Table $table): Table
    {
        return PermissionHoldersTable::configure($table, $this->selectedPermission(...));
    }

    public function selectedPermission(): ?AdminPermission
    {
        return $this->permission === null ? null : AdminPermission::tryFrom($this->permission);
    }

    /**
     * The roles granting the chosen permission, in display order, with their users, their share of the
     * holders and a link to their detail when the administrator may open it.
     *
     * @return list<array{name: string, label: string, isSystem: bool, usersCount: int, share: int, url: string|null}>
     */
    public function getHolderRoles(): array
    {
        $adminPermission = $this->selectedPermission();

        if (! $adminPermission instanceof AdminPermission) {
            return [];
        }

        $roles = Role::grantingPermission(Role::query(), $adminPermission)->withCount('users')->get();
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
     * The holders of the chosen permission and how many fail each panel-entry requirement: suspended,
     * without panel MFA, unverified, or without `admin.access` (skipped when that is the permission).
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

        $summary = self::aggregateCounts(self::holdersQuery($adminPermission)
            ->selectRaw(
                'count(*) as holders, '
                .'coalesce(sum(case when status = ? then 1 else 0 end), 0) as suspended, '
                .'coalesce(sum(case when app_authentication_secret is null then 1 else 0 end), 0) as without_mfa, '
                .'coalesce(sum(case when email_verified_at is null then 1 else 0 end), 0) as unverified',
                [AccountStatus::Suspended->value],
            ), ['holders', 'suspended', 'without_mfa', 'unverified']);

        return $this->holderSummary = [
            'holders' => $summary['holders'],
            'suspended' => $summary['suspended'],
            'withoutMfa' => $summary['without_mfa'],
            'unverified' => $summary['unverified'],
            'withoutPanelAccess' => $adminPermission === AdminPermission::AdminAccess
                ? 0
                : self::holdersQuery($adminPermission)
                    ->whereDoesntHave('roles', static fn (Builder $roles): Builder => Role::grantingPermission($roles, AdminPermission::AdminAccess))
                    ->count(),
        ];
    }

    /**
     * The permissions the chosen one needs and the ones that need it, each linked to this page.
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
     * The users holding the permission through any role of the `admin` guard, each once.
     *
     * @return Builder<User>
     */
    public static function holdersQuery(AdminPermission $adminPermission): Builder
    {
        return User::query()->whereHas('roles', static fn (Builder $roles): Builder => Role::grantingPermission($roles, $adminPermission));
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
}

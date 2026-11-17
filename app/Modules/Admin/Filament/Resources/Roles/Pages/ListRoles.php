<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\RolesOverview;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\RoleTypesOverview;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\UsersPerRoleChart;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Override;

class ListRoles extends ListRecords
{
    #[Override]
    protected static string $resource = RoleResource::class;

    /**
     * @return Action[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createRole')
                ->label(TranslatedText::of('admin/roles.actions.create.label'))
                ->icon(Heroicon::OutlinedPlus)
                ->authorize('create')
                ->url(RoleResource::getUrl('create')),
            Action::make('permissionHolders')
                ->label(TranslatedText::of('admin/roles.access.title'))
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->visible(static fn (): bool => PermissionHolders::canAccess())
                ->url(static fn (): string => RoleResource::getUrl('access')),
            Action::make('openMatrix')
                ->label(TranslatedText::of('admin/roles.actions.open_matrix.label'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(RoleResource::getUrl('matrix')),
        ];
    }

    /**
     * Split the roles by type, each tab with how many roles of the `admin` guard it holds.
     *
     * @return array<string, Tab>
     */
    #[Override]
    public function getTabs(): array
    {
        $systemRoleCount = Role::query()->where('guard_name', Role::GUARD_NAME)->where('is_system', true)->count();
        $roleCount = Role::query()->where('guard_name', Role::GUARD_NAME)->count();

        return [
            'all' => Tab::make(TranslatedText::of('admin/roles.types.all'))
                ->badge($roleCount),
            'system' => Tab::make(TranslatedText::of('admin/roles.types.system'))
                ->badge($systemRoleCount)
                ->modifyQueryUsing(static function (Builder $query): Builder {
                    $query->getQuery()->where('is_system', true);

                    return $query;
                }),
            'custom' => Tab::make(TranslatedText::of('admin/roles.types.custom'))
                ->badge($roleCount - $systemRoleCount)
                ->modifyQueryUsing(static function (Builder $query): Builder {
                    $query->getQuery()->where('is_system', false);

                    return $query;
                }),
        ];
    }

    #[Override]
    public function getDefaultActiveTab(): string
    {
        return 'all';
    }

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            RolesOverview::class,
            RoleTypesOverview::class,
            UsersPerRoleChart::class,
        ];
    }
}

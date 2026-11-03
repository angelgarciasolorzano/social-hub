<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\RolesOverview;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\UsersPerRoleChart;
use App\Support\Translation\TranslatedText;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
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
            Action::make('openMatrix')
                ->label(TranslatedText::of('admin/roles.actions.open_matrix.label'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(RoleResource::getUrl('matrix')),
        ];
    }

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            RolesOverview::class,
            UsersPerRoleChart::class,
        ];
    }
}

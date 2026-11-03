<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\RolesOverview;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\UsersPerRoleChart;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;
use Override;

class ListRoles extends ListRecords
{
    #[Override]
    protected static string $resource = RoleResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
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

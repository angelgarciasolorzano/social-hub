<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ListRecords;
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
}

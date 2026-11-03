<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ViewRecord;
use Override;

class ViewRole extends ViewRecord
{
    #[Override]
    protected static string $resource = RoleResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}

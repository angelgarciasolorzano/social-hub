<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs\Pages;

use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListActivityLogs extends ListRecords
{
    #[Override]
    protected static string $resource = ActivityLogResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}

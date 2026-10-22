<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs\Pages;

use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ViewRecord;
use Override;

class ViewActivityLog extends ViewRecord
{
    #[Override]
    protected static string $resource = ActivityLogResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}

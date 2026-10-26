<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs\Pages;

use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Widgets\ActivityLogOverview;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Widgets\ActivityLogSummaryWidget;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;
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

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            ActivityLogOverview::class,
            ActivityLogSummaryWidget::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;
use Override;

class ListUsers extends ListRecords
{
    #[Override]
    protected static string $resource = UserResource::class;

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
            RegistrationSummaryWidget::class,
        ];
    }
}

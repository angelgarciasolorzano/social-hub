<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Resources\Users\Widgets\UserActivityOverview;
use Filament\Resources\Pages\ViewRecord;
use Override;

class ViewUser extends ViewRecord
{
    #[Override]
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            UserActivityOverview::class,
        ];
    }
}

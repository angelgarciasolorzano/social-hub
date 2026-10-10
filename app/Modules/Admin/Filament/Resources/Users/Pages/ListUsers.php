<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListUsers extends ListRecords
{
    #[Override]
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

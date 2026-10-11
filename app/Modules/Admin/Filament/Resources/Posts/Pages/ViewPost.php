<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Pages;

use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\ViewRecord;
use Override;

class ViewPost extends ViewRecord
{
    #[Override]
    protected static string $resource = PostResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Pages;

use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListPosts extends ListRecords
{
    #[Override]
    protected static string $resource = PostResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}

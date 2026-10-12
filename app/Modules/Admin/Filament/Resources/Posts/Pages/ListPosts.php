<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Pages;

use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Posts\Widgets\PostsOverview;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;
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

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            PostsOverview::class,
        ];
    }
}

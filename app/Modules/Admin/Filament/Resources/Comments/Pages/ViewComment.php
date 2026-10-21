<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments\Pages;

use App\Modules\Admin\Filament\Resources\Comments\CommentResource;
use App\Modules\Admin\Filament\Resources\Comments\Widgets\CommentActivityOverview;
use Filament\Resources\Pages\ViewRecord;
use Override;

class ViewComment extends ViewRecord
{
    #[Override]
    protected static string $resource = CommentResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            CommentActivityOverview::class,
        ];
    }

    #[Override]
    public function hasResourceBreadcrumbs(): bool
    {
        return false;
    }
}

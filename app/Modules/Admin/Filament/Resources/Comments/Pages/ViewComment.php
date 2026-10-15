<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments\Pages;

use App\Modules\Admin\Filament\Resources\Comments\CommentResource;
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
    public function hasResourceBreadcrumbs(): bool
    {
        return false;
    }
}

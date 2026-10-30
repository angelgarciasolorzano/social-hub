<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Pages;

use App\Modules\Admin\Filament\Concerns\BuildsAnnouncementReviewActions;
use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Override;

class ViewAnnouncement extends ViewRecord
{
    use BuildsAnnouncementReviewActions;

    #[Override]
    protected static string $resource = AnnouncementResource::class;

    /**
     * @return array<Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ...self::announcementReviewActions(),
            EditAction::make(),
        ];
    }
}

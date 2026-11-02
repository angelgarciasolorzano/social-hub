<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Pages;

use App\Modules\Admin\Filament\Resources\Announcements\Actions\ApproveAnnouncementAction;
use App\Modules\Admin\Filament\Resources\Announcements\Actions\RejectAnnouncementAction;
use App\Modules\Admin\Filament\Resources\Announcements\Actions\SubmitAnnouncementForReviewAction;
use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Override;

class ViewAnnouncement extends ViewRecord
{
    #[Override]
    protected static string $resource = AnnouncementResource::class;

    /**
     * @return array<Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            SubmitAnnouncementForReviewAction::make(),
            ApproveAnnouncementAction::make(),
            RejectAnnouncementAction::make(),
            EditAction::make(),
        ];
    }
}

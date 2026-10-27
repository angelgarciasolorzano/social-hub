<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Pages;

use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Announcement\Data\AnnouncementData;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Services\AnnouncementService;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use LogicException;
use Override;

class CreateAnnouncement extends CreateRecord
{
    #[Override]
    protected static string $resource = AnnouncementResource::class;

    /**
     * Create the announcement through the service, so it records its author and its audit;
     * the image field saves the image afterwards.
     *
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Announcement
    {
        $authenticatedUser = Filament::auth()->user();

        throw_unless($authenticatedUser instanceof User, LogicException::class, 'Only a signed-in administrator can create announcements.');

        return resolve(AnnouncementService::class)->create(AnnouncementData::fromFormState($data), $authenticatedUser);
    }
}

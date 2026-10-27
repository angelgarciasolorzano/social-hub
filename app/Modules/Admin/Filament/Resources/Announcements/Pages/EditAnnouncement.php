<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Pages;

use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Announcement\Data\AnnouncementData;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Services\AnnouncementService;
use App\User\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Override;

class EditAnnouncement extends EditRecord
{
    #[Override]
    protected static string $resource = AnnouncementResource::class;

    /**
     * @return array<DeleteAction>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Announcement $record): bool {
                    resolve(AnnouncementService::class)->delete($record, $this->administrator());

                    return true;
                }),
        ];
    }

    /**
     * Save the changes through the service, so only real changes are audited;
     * the image field saves the image afterwards.
     *
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Announcement
    {
        throw_unless($record instanceof Announcement, LogicException::class, 'Only announcements can be edited here.');

        return resolve(AnnouncementService::class)->update($record, AnnouncementData::fromFormState($data), $this->administrator());
    }

    private function administrator(): User
    {
        $authenticatedUser = Filament::auth()->user();

        throw_unless($authenticatedUser instanceof User, LogicException::class, 'Only a signed-in administrator can change announcements.');

        return $authenticatedUser;
    }
}

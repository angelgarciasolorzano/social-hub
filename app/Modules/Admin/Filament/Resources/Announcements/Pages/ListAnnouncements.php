<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Pages;

use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Announcement\Models\Announcement;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Override;

class ListAnnouncements extends ListRecords
{
    #[Override]
    protected static string $resource = AnnouncementResource::class;

    /**
     * Split the announcements by their schedule, with the same date rule the application uses to show them,
     * plus the running dialogs with their count, so administrators notice how many interrupt users at once.
     *
     * @return array<string, Tab>
     */
    #[Override]
    public function getTabs(): array
    {
        return [
            'scheduled' => Tab::make('Scheduled')->modifyQueryUsing($this->onlyScheduled(...)),
            'active' => Tab::make('Active')->modifyQueryUsing($this->onlyRunning(...)),
            'expired' => Tab::make('Expired')->modifyQueryUsing($this->onlyEnded(...)),
            'active_dialogs' => Tab::make('Active dialogs')
                ->modifyQueryUsing($this->onlyRunningDialogs(...))
                ->badge(Announcement::query()->running()->dialogs()->count())
                ->badgeColor('warning')
                ->badgeTooltip('Dialogs interrupt users until they close them'),
            'all' => Tab::make('All'),
        ];
    }

    /**
     * @return CreateAction[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    #[Override]
    public function getDefaultActiveTab(): string
    {
        return 'active';
    }

    /**
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    private function onlyScheduled(Builder $query): Builder
    {
        return $query->scheduled();
    }

    /**
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    private function onlyRunning(Builder $query): Builder
    {
        return $query->running();
    }

    /**
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    private function onlyRunningDialogs(Builder $query): Builder
    {
        return $query->running()->dialogs();
    }

    /**
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    private function onlyEnded(Builder $query): Builder
    {
        return $query->ended();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Pages;

use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Announcement\Models\Announcement;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Override;

class ListAnnouncements extends ListRecords
{
    #[Override]
    protected static string $resource = AnnouncementResource::class;

    /**
     * Split the announcements by their schedule, with the same date rule the application uses to show them.
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
            'all' => Tab::make('All'),
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
    private function onlyEnded(Builder $query): Builder
    {
        return $query->ended();
    }
}

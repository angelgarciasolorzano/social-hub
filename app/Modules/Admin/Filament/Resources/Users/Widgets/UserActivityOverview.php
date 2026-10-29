<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Widgets;

use App\Modules\Admin\Filament\Concerns\BuildsDailyActivityCharts;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class UserActivityOverview extends StatsOverviewWidget
{
    use BuildsDailyActivityCharts;

    public ?Model $record = null;

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', User::class);
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $record = $this->record;

        if (! $record instanceof User) {
            return [];
        }

        $startDate = CarbonImmutable::today()->subDays(6);
        $endDate = $startDate->addDays(7);
        $statusChangesStartDate = CarbonImmutable::today()->subDays(29)->startOfDay();
        $statusChangesEndDate = CarbonImmutable::today()->endOfDay();
        $statusChangesCount = $record->accountStatusChanges()
            ->whereBetween('created_at', [$statusChangesStartDate, $statusChangesEndDate])
            ->count();

        return [
            Stat::make(TranslatedText::of('admin/users.widgets.activity_overview.posts.label'), $record->posts()->count())
                ->description(TranslatedText::of('admin/users.widgets.activity_overview.posts.description'))
                ->chart($this->getDailyActivityChartData($record->posts()->getQuery(), $startDate, $endDate))
                ->color('primary'),
            Stat::make(TranslatedText::of('admin/users.widgets.activity_overview.comments.label'), $record->comments()->count())
                ->description(TranslatedText::of('admin/users.widgets.activity_overview.comments.description'))
                ->chart($this->getDailyActivityChartData($record->comments()->getQuery(), $startDate, $endDate))
                ->color('info'),
            Stat::make(
                TranslatedText::of('admin/users.widgets.activity_overview.active_trusted_devices.label'),
                $record->trustedDevices()
                    ->whereNull('deleted_at')
                    ->where('expires_at', '>', now())
                    ->count(),
            )->description(TranslatedText::of('admin/users.widgets.activity_overview.active_trusted_devices.description')),
            Stat::make(TranslatedText::of('admin/users.widgets.activity_overview.status_changes.label'), $statusChangesCount)
                ->description(TranslatedText::of('admin/users.widgets.activity_overview.status_changes.description'))
                ->color('warning'),
        ];
    }
}

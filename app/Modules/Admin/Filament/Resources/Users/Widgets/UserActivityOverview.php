<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Widgets;

use App\Modules\Admin\Filament\Concerns\BuildsDailyActivityCharts;
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

        return [
            Stat::make('Posts', $record->posts()->count())
                ->description('New posts per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($record->posts()->getQuery(), $startDate, $endDate))
                ->color('primary'),
            Stat::make('Comments', $record->comments()->count())
                ->description('New comments per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($record->comments()->getQuery(), $startDate, $endDate))
                ->color('info'),
            Stat::make(
                'Active trusted devices',
                $record->trustedDevices()
                    ->whereNull('deleted_at')
                    ->where('expires_at', '>', now())
                    ->count(),
            )->description('Not revoked and not expired'),
        ];
    }
}

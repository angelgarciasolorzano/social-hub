<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Widgets;

use App\Modules\Admin\Enums\AdminPermission;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class UserActivityOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::UsersView->value);
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
                ->chart($this->getDailyActivityChartData($record->posts(), $startDate, $endDate))
                ->color('primary'),
            Stat::make('Comments', $record->comments()->count())
                ->description('New comments per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($record->comments(), $startDate, $endDate))
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

    /**
     * @template TRelatedModel of Model
     *
     * @param  HasMany<TRelatedModel, User>  $hasMany
     * @return array<float>
     */
    private function getDailyActivityChartData(
        HasMany $hasMany,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): array {
        $activityCountsByDate = $hasMany->getQuery()
            ->toBase()
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<', $endDate)
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'activity_date');

        return collect(range(0, 6))
            ->map(function (int $dayOffset) use ($activityCountsByDate, $startDate): float {
                $activityCount = $activityCountsByDate->get(
                    $startDate->addDays($dayOffset)->toDateString(),
                    0,
                );

                return is_numeric($activityCount) ? (float) $activityCount : 0.0;
            })
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BuildsDailyActivityCharts
{
    /**
     * The number of rows of each day from the start date, oldest first, by the given date column.
     * A day without rows counts 0, and rows outside `[startDate, endDate)` do not count.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $dateColumn
     * @return array<float>
     */
    protected function getDailyActivityChartData(
        Builder $query,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        int $days = 7,
        string $dateColumn = 'created_at',
    ): array {
        $activityCountsByDate = (clone $query)
            ->toBase()
            ->where($dateColumn, '>=', $startDate)
            ->where($dateColumn, '<', $endDate)
            ->selectRaw('DATE('.$dateColumn.') as activity_date, COUNT(*) as aggregate')
            ->groupByRaw('DATE('.$dateColumn.')')
            ->pluck('aggregate', 'activity_date');

        return collect(range(0, $days - 1))
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

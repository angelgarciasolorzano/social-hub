<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BuildsDailyActivityCharts
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array<float>
     */
    protected function getDailyActivityChartData(
        Builder $query,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): array {
        $activityCountsByDate = (clone $query)
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

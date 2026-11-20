<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Admin\Filament\Concerns\BuildsDailyActivityCharts;
use App\Modules\Admin\Filament\Concerns\ReadsAggregateCounts;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\Friendship;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;
use Override;

class FriendshipsOverviewWidget extends StatsOverviewWidget
{
    use BuildsDailyActivityCharts;
    use ReadsAggregateCounts;

    private const int CHART_DAYS = 30;

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    protected function getHeading(): string
    {
        return TranslatedText::of('admin/dashboard.friendships_overview.heading');
    }

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Friendship::class);
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $startDate = CarbonImmutable::today()->subDays(self::CHART_DAYS - 1);
        $endDate = $startDate->addDays(self::CHART_DAYS);
        $totals = $this->getTotals($startDate, $endDate);

        return [
            Stat::make(TranslatedText::of('admin/dashboard.friendships_overview.accepted.label'), $totals['accepted'])
                ->description(TranslatedText::of('admin/dashboard.friendships_overview.accepted.description'))
                ->chart($this->getDailyActivityChartData(Friendship::query()->accepted(), $startDate, $endDate, self::CHART_DAYS, 'accepted_at'))
                ->color('success'),
            Stat::make(TranslatedText::of('admin/dashboard.friendships_overview.pending.label'), $totals['pending'])
                ->description(TranslatedText::of('admin/dashboard.friendships_overview.pending.description'))
                ->chart($this->getDailyActivityChartData(Friendship::query()->pending(), $startDate, $endDate, self::CHART_DAYS))
                ->color('warning'),
            Stat::make(TranslatedText::of('admin/dashboard.friendships_overview.new.label'), $totals['new'])
                ->description(TranslatedText::of('admin/dashboard.friendships_overview.new.description'))
                ->color('primary'),
        ];
    }

    /**
     * The accepted and pending totals and the friendships accepted in the chart range, in one query.
     *
     * @return array{accepted: int, pending: int, new: int}
     */
    private function getTotals(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $counts = self::aggregateCounts(Friendship::query()
            ->toBase()
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted', [FriendshipStatus::Accepted->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [FriendshipStatus::Pending->value])
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND accepted_at >= ? AND accepted_at < ? THEN 1 ELSE 0 END) as new',
                [FriendshipStatus::Accepted->value, $startDate, $endDate],
            ), ['accepted', 'pending', 'new']);

        return [
            'accepted' => $counts['accepted'],
            'pending' => $counts['pending'],
            'new' => $counts['new'],
        ];
    }
}

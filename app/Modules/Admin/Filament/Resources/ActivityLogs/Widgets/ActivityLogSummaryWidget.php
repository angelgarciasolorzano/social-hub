<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs\Widgets;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Support\ActivityLogEventCatalog;
use App\Modules\Admin\Data\ActivityLogSummaryData;
use App\Modules\Admin\Enums\AdminPermission;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Override;
use stdClass;
use UnexpectedValueException;

class ActivityLogSummaryWidget extends ChartWidget
{
    private const string UNKNOWN_EVENT_LABEL = 'Unspecified event';

    #[Override]
    protected int|string|array $columnSpan = 'full';

    #[Override]
    protected ?string $heading = 'Activity events (last 7 days)';

    #[Override]
    protected ?string $emptyStateHeading = 'No activity in the last 7 days';

    #[Override]
    protected ?string $emptyStateDescription = 'There are no activity events to summarize for this period.';

    #[Override]
    protected ?string $maxHeight = '360px';

    #[Override]
    protected ?array $options = ['indexAxis' => 'y'];

    private ?ActivityLogSummaryData $summary = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminActivityViewAny->value);
    }

    /**
     * @return array{}|array{datasets: list<array{label: string, data: list<int>}>, labels: list<string>}
     */
    #[Override]
    protected function getData(): array
    {
        $activityLogSummaryData = $this->getSummary();

        if ($activityLogSummaryData->totalEvents === 0) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Events',
                'data' => $activityLogSummaryData->eventCounts,
            ]],
            'labels' => $activityLogSummaryData->eventLabels,
        ];
    }

    #[Override]
    public function getDescription(): string
    {
        $totalEvents = $this->getSummary()->totalEvents;
        $eventNoun = $totalEvents === 1 ? 'event' : 'events';

        return "{$totalEvents} {$eventNoun} in the last 7 days.";
    }

    #[Override]
    public function getChartAssistiveContent(): string
    {
        $activityLogSummaryData = $this->getSummary();

        if ($activityLogSummaryData->totalEvents === 0) {
            return 'There are no activity events to summarize for the last 7 days.';
        }

        $eventCountsByLabel = array_map(
            static fn (string $eventLabel, int $eventCount): string => "{$eventLabel}: {$eventCount}",
            $activityLogSummaryData->eventLabels,
            $activityLogSummaryData->eventCounts,
        );

        return 'Event distribution: '.implode(', ', $eventCountsByLabel).'.';
    }

    #[Override]
    protected function getType(): string
    {
        return 'bar';
    }

    private function getSummary(): ActivityLogSummaryData
    {
        if ($this->summary instanceof ActivityLogSummaryData) {
            return $this->summary;
        }

        $now = CarbonImmutable::now();
        $start = $now->startOfDay()->subDays(6);
        $end = $now->endOfDay();

        $activityCounts = ActivityLog::query()
            ->toBase()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("COALESCE(event, '".self::UNKNOWN_EVENT_LABEL."') as event_name, COUNT(*) as event_count")
            ->groupByRaw("COALESCE(event, '".self::UNKNOWN_EVENT_LABEL."')")
            ->orderByDesc('event_count')
            ->orderBy('event_name')
            ->get();

        $this->summary = $this->buildSummary($activityCounts);

        return $this->summary;
    }

    /**
     * @param  Collection<int, stdClass>  $activityCounts
     */
    private function buildSummary(Collection $activityCounts): ActivityLogSummaryData
    {
        $eventLabels = [];
        $eventCounts = [];
        $totalEvents = 0;

        foreach ($activityCounts as $activityCount) {
            $eventName = $activityCount->event_name;
            $eventCount = $activityCount->event_count;

            throw_if(! \is_string($eventName)
                || (! \is_int($eventCount) && (! \is_string($eventCount) || ! ctype_digit($eventCount))), UnexpectedValueException::class, 'The activity aggregate returned an invalid value.');

            $eventLabels[] = $eventName === self::UNKNOWN_EVENT_LABEL
                ? self::UNKNOWN_EVENT_LABEL
                : ActivityLogEventCatalog::label($eventName);
            $eventCounts[] = (int) $eventCount;
            $totalEvents += (int) $eventCount;
        }

        return new ActivityLogSummaryData($totalEvents, $eventLabels, $eventCounts);
    }
}

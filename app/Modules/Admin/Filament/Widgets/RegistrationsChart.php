<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Admin\Enums\AdminPermission;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Override;
use UnexpectedValueException;

class RegistrationsChart extends ChartWidget
{
    private const string FILTER_7_DAYS = '7_days';

    private const string FILTER_30_DAYS = '30_days';

    private const string FILTER_90_DAYS = '90_days';

    private const string FILTER_12_MONTHS = '12_months';

    #[Override]
    public ?string $filter = self::FILTER_30_DAYS;

    #[Override]
    protected string $color = 'primary';

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public function getHeading(): string
    {
        return TranslatedText::of('admin/dashboard.registrations_chart.heading');
    }

    #[Override]
    public function getDescription(): string
    {
        return TranslatedText::of('admin/dashboard.registrations_chart.description');
    }

    #[Override]
    public function getEmptyStateHeading(): string
    {
        return TranslatedText::of('admin/dashboard.registrations_chart.empty_heading');
    }

    #[Override]
    public function getEmptyStateDescription(): string
    {
        return TranslatedText::of('admin/dashboard.registrations_chart.empty_description');
    }

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminDashboardView->value);
    }

    /**
     * @return array{}|array{datasets: list<array{label: string, data: list<int>}>, labels: list<string>}
     */
    protected function getData(): array
    {
        $range = $this->getSelectedRange();

        $registrationsByDate = User::query()
            ->toBase()
            ->whereBetween('created_at', [$range['start'], $range['end']], 'and')
            ->selectRaw('DATE(created_at) as registration_date, COUNT(*) as total')
            ->groupBy('registration_date')
            ->oldest('registration_date')
            ->get();

        if ($registrationsByDate->isEmpty()) {
            return [];
        }

        $buckets = $this->getBuckets($range['start'], $range['end'], $range['granularity']);
        $bucketCounts = array_fill_keys(array_column($buckets, 'key'), 0);

        foreach ($registrationsByDate as $registrations) {
            $registrationDateValue = $registrations->registration_date;
            $registrationTotal = $registrations->total;

            throw_if(! \is_string($registrationDateValue)
                || (! \is_int($registrationTotal) && (! \is_string($registrationTotal) || ! ctype_digit($registrationTotal))), UnexpectedValueException::class, 'The registration aggregate returned an invalid value.');

            $registrationDate = CarbonImmutable::parse($registrationDateValue);

            $bucketDate = match ($range['granularity']) {
                'daily' => $registrationDate,
                'weekly' => $registrationDate->startOfWeek(),
                'monthly' => $registrationDate->startOfMonth(),
            };

            $bucketKey = $bucketDate->toDateString();
            $bucketCounts[$bucketKey] = ($bucketCounts[$bucketKey] ?? 0) + (int) $registrationTotal;
        }

        $labels = [];
        $counts = [];

        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];
            $counts[] = $bucketCounts[$bucket['key']];
        }

        return [
            'datasets' => [[
                'label' => TranslatedText::of('admin/dashboard.registrations_chart.dataset'),
                'data' => $counts,
            ]],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            self::FILTER_7_DAYS => TranslatedText::of('admin/dashboard.registrations_chart.filters.7_days'),
            self::FILTER_30_DAYS => TranslatedText::of('admin/dashboard.registrations_chart.filters.30_days'),
            self::FILTER_90_DAYS => TranslatedText::of('admin/dashboard.registrations_chart.filters.90_days'),
            self::FILTER_12_MONTHS => TranslatedText::of('admin/dashboard.registrations_chart.filters.12_months'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, granularity: 'daily'|'weekly'|'monthly'}
     */
    private function getSelectedRange(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $end = $today->endOfDay();

        return match ($this->filter) {
            self::FILTER_7_DAYS => [
                'start' => $today->subDays(6),
                'end' => $end,
                'granularity' => 'daily',
            ],
            self::FILTER_90_DAYS => [
                'start' => $today->subDays(89),
                'end' => $end,
                'granularity' => 'weekly',
            ],
            self::FILTER_12_MONTHS => [
                'start' => $today->startOfMonth()->subMonths(11),
                'end' => $end,
                'granularity' => 'monthly',
            ],
            default => [
                'start' => $today->subDays(29),
                'end' => $end,
                'granularity' => 'daily',
            ],
        };
    }

    /**
     * @param  'daily'|'weekly'|'monthly'  $granularity
     * @return list<array{key: string, label: string}>
     */
    private function getBuckets(CarbonImmutable $start, CarbonImmutable $end, string $granularity): array
    {
        $bucket = match ($granularity) {
            'daily' => $start->startOfDay(),
            'weekly' => $start->startOfWeek(),
            'monthly' => $start->startOfMonth(),
        };

        $lastBucket = match ($granularity) {
            'daily' => $end->startOfDay(),
            'weekly' => $end->startOfWeek(),
            'monthly' => $end->startOfMonth(),
        };

        $dateFormat = TranslatedText::of('admin/dashboard.registrations_chart.date_formats.'.($granularity === 'monthly' ? 'month' : 'day'));
        $buckets = [];

        while ($bucket->getTimestamp() <= $lastBucket->getTimestamp()) {
            $buckets[] = [
                'key' => $bucket->toDateString(),
                'label' => $bucket->translatedFormat($dateFormat),
            ];

            $bucket = match ($granularity) {
                'daily' => $bucket->addDay(),
                'weekly' => $bucket->addWeek(),
                'monthly' => $bucket->addMonth(),
            };
        }

        return $buckets;
    }
}

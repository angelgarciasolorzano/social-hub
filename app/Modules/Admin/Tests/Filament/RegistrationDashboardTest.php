<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Widgets\RegistrationsChart;
use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use ReflectionMethod;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->seed(AdminAuthorizationSeeder::class);

    Filament::setCurrentPanel('admin');

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);
    $user->forceFill(['created_at' => '2020-01-01 00:00:00'])->save();

    $this->actingAs($user, 'admin');
});

it('discovers the registration widgets through the existing admin panel discovery', function (): void {
    expect(Filament::getPanel('admin')->getWidgets())
        ->toContain(RegistrationsChart::class, RegistrationSummaryWidget::class);
});

it('groups registrations by day for the seven day range and includes its exact boundaries', function (): void {
    createDashboardRegistration('2026-06-09 00:00:00');
    createDashboardRegistration('2026-06-10 10:00:00');
    createDashboardRegistration('2026-06-10 18:00:00');
    createDashboardRegistration('2026-06-08 23:59:59');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => '7_days'])->instance();
    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    $chartData = registrationChartPayload($component);

    expect($chartData['labels'])
        ->toHaveCount(7)
        ->toBe(['Jun 9', 'Jun 10', 'Jun 11', 'Jun 12', 'Jun 13', 'Jun 14', 'Jun 15'])
        ->and($chartData['datasets'][0]['data'])
        ->toBe([1, 2, 0, 0, 0, 0, 0]);
});

it('groups registrations by day for the thirty day range', function (): void {
    createDashboardRegistration('2026-05-17 00:00:00');
    createDashboardRegistration('2026-06-15 23:59:59');
    createDashboardRegistration('2026-05-16 23:59:59');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => '30_days'])->instance();
    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    $chartData = registrationChartPayload($component);

    expect($chartData['labels'])
        ->toHaveCount(30)
        ->and(array_slice($chartData['labels'], 0, 1))
        ->toBe(['May 17'])
        ->and(array_slice($chartData['labels'], -1))
        ->toBe(['Jun 15'])
        ->and(array_sum($chartData['datasets'][0]['data']))
        ->toBe(2);
});

it('groups registrations by week for the ninety day range', function (): void {
    createDashboardRegistration('2026-03-18 00:00:00');
    createDashboardRegistration('2026-03-17 23:59:59');
    createDashboardRegistration('2026-06-10 10:00:00');
    createDashboardRegistration('2026-06-14 18:00:00');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => '90_days'])->instance();
    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    $chartData = registrationChartPayload($component);
    $weeklyCounts = array_combine($chartData['labels'], $chartData['datasets'][0]['data']);

    expect($chartData['labels'])
        ->toHaveCount(14)
        ->and(array_slice($chartData['labels'], 0, 1))
        ->toBe(['Mar 16'])
        ->and(array_slice($chartData['labels'], -1))
        ->toBe(['Jun 15'])
        ->and($weeklyCounts['Mar 16'])
        ->toBe(1)
        ->and($weeklyCounts['Jun 8'])
        ->toBe(2);
});

it('groups registrations by month for the twelve month range', function (): void {
    createDashboardRegistration('2025-07-01 00:00:00');
    createDashboardRegistration('2025-06-30 23:59:59');
    createDashboardRegistration('2026-06-01 10:00:00');
    createDashboardRegistration('2026-06-15 18:00:00');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => '12_months'])->instance();
    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    $chartData = registrationChartPayload($component);
    $monthlyCounts = array_combine($chartData['labels'], $chartData['datasets'][0]['data']);

    expect($chartData['labels'])
        ->toHaveCount(12)
        ->and(array_slice($chartData['labels'], 0, 1))
        ->toBe(['Jul 2025'])
        ->and(array_slice($chartData['labels'], -1))
        ->toBe(['Jun 2026'])
        ->and($monthlyCounts['Jul 2025'])
        ->toBe(1)
        ->and($monthlyCounts['Jun 2026'])
        ->toBe(2);
});

it('falls back to the thirty day range for an unknown client supplied filter', function (): void {
    createDashboardRegistration('2026-05-17 00:00:00');
    createDashboardRegistration('2026-05-16 23:59:59');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => 'untrusted-filter-value'])->instance();
    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    $chartData = registrationChartPayload($component);

    expect($chartData['labels'])
        ->toHaveCount(30)
        ->and(array_sum($chartData['datasets'][0]['data']))
        ->toBe(1);
});

it('renders filaments empty state when the selected period has no registrations', function (): void {
    Livewire::test(RegistrationsChart::class)
        ->assertSeeText('No registrations yet')
        ->assertSeeText('There are no account registrations in this period.');
});

it('shows accurate total and verified account summaries', function (): void {
    createDashboardRegistration('2026-06-10 10:00:00');
    createDashboardRegistration('2026-06-11 10:00:00', verified: false);

    Livewire::test(RegistrationSummaryWidget::class)
        ->assertSeeText('Total accounts')
        ->assertSeeText('All registered user accounts')
        ->assertSeeText('Verified accounts')
        ->assertSeeText('Accounts with a verified email address')
        ->assertSeeText('3')
        ->assertSeeText('2');
});

it('shows seven day registration trends on the account summary cards', function (): void {
    createDashboardRegistration('2026-06-09 08:00:00');
    createDashboardRegistration('2026-06-10 08:00:00', verified: false);
    createDashboardRegistration('2026-06-10 09:00:00');
    createDashboardRegistration('2026-06-15 08:00:00', verified: false);

    $component = Livewire::test(RegistrationSummaryWidget::class)->instance();
    throw_unless($component instanceof RegistrationSummaryWidget, LogicException::class, 'The summary widget must be mounted.');

    $statsMethod = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats');
    $stats = $statsMethod->invoke($component);

    throw_unless(
        is_array($stats)
            && count($stats) === 2
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat,
        LogicException::class,
        'The account summary must return two stat cards.',
    );

    expect($stats[0]->getChart())
        ->toBe([1.0, 2.0, 0.0, 0.0, 0.0, 0.0, 1.0])
        ->and($stats[1]->getChart())
        ->toBe([1.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
});

function createDashboardRegistration(string $createdAt, bool $verified = true): User
{
    $user = createUser();

    $user->forceFill([
        'created_at' => $createdAt,
        'email_verified_at' => $verified ? now() : null,
    ])->save();

    return $user;
}

/**
 * @return array{datasets: list<array{label: string, data: list<int>}>, labels: list<string>}
 */
function registrationChartPayload(RegistrationsChart $registrationsChart): array
{
    $cachedDataMethod = new ReflectionMethod(ChartWidget::class, 'getCachedData');
    $chartData = $cachedDataMethod->invoke($registrationsChart);

    $labels = is_array($chartData) ? ($chartData['labels'] ?? null) : null;
    $datasets = is_array($chartData) ? ($chartData['datasets'] ?? null) : null;
    $firstDataset = is_array($datasets) ? ($datasets[0] ?? null) : null;
    $datasetLabel = is_array($firstDataset) ? ($firstDataset['label'] ?? null) : null;
    $counts = is_array($firstDataset) ? ($firstDataset['data'] ?? null) : null;

    throw_unless(
        is_array($labels)
            && array_is_list($labels)
            && is_array($counts)
            && array_is_list($counts)
            && is_string($datasetLabel),
        LogicException::class,
        'The registration chart must return its chart data.',
    );

    $normalizedLabels = [];

    foreach ($labels as $label) {
        throw_unless(is_string($label), LogicException::class, 'The chart registration labels must be strings.');

        $normalizedLabels[] = $label;
    }

    $normalizedCounts = [];

    foreach ($counts as $count) {
        throw_unless(is_int($count), LogicException::class, 'The chart registration counts must be integers.');

        $normalizedCounts[] = $count;
    }

    return [
        'datasets' => [[
            'label' => $datasetLabel,
            'data' => $normalizedCounts,
        ]],
        'labels' => $normalizedLabels,
    ];
}

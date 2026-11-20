<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Concerns\BuildsDailyActivityCharts;
use App\User\Models\User;
use Carbon\CarbonImmutable;

/**
 * The daily chart of the users table. The concern is used by an anonymous class: a named class in
 * a test file would break Composer's PSR-4 autoload map.
 *
 * @param  literal-string  $dateColumn
 * @return array<float>
 */
function dailyUsersChart(CarbonImmutable $startDate, int $days, string $dateColumn = 'created_at'): array
{
    $probe = new class
    {
        use BuildsDailyActivityCharts;

        /**
         * @param  literal-string  $dateColumn
         * @return array<float>
         */
        public function build(CarbonImmutable $startDate, int $days, string $dateColumn): array
        {
            return $this->getDailyActivityChartData(User::query(), $startDate, $startDate->addDays($days), $days, $dateColumn);
        }
    };

    return $probe->build($startDate, $days, $dateColumn);
}

function createUserWithDates(CarbonImmutable $createdAt, ?CarbonImmutable $verifiedAt = null): void
{
    createUser()->forceFill(['created_at' => $createdAt, 'email_verified_at' => $verifiedAt])->save();
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-30 12:00:00'));
});

it('returns one value per day, oldest first, for the requested number of days', function (): void {
    $startDate = CarbonImmutable::today()->subDays(29);
    createUserWithDates($startDate->setTime(9, 0));
    createUserWithDates($startDate->addDays(29)->setTime(23, 59));
    createUserWithDates($startDate->addDays(29)->setTime(1, 0));

    $chart = dailyUsersChart($startDate, 30);

    expect($chart)->toHaveCount(30)
        ->and($chart[0])->toBe(1.0)
        ->and($chart[29])->toBe(2.0)
        ->and(array_sum($chart))->toBe(3.0);
});

it('counts by the requested date column', function (): void {
    $startDate = CarbonImmutable::today()->subDays(6);
    createUserWithDates($startDate->subYear(), $startDate->addDays(2)->setTime(10, 0));
    createUserWithDates($startDate->addDay()->setTime(10, 0));

    expect(dailyUsersChart($startDate, 7, 'email_verified_at'))->toBe([0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0])
        ->and(dailyUsersChart($startDate, 7))->toBe([0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
});

it('ignores rows outside the range and keeps empty days at zero', function (): void {
    $startDate = CarbonImmutable::today()->subDays(6);
    createUserWithDates($startDate->subSecond());
    createUserWithDates($startDate->addDays(7));

    expect(dailyUsersChart($startDate, 7))->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
});

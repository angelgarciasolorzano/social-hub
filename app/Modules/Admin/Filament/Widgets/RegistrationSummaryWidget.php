<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Admin\Enums\AdminPermission;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;
use UnexpectedValueException;

class RegistrationSummaryWidget extends StatsOverviewWidget
{
    #[Override]
    protected ?string $heading = 'Account overview';

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && (
                $authenticatedUser->can(AdminPermission::AdminDashboardView->value)
                || $authenticatedUser->can(AdminPermission::AdminUsersViewAny->value)
            );
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $dailyRegistrationTrends = $this->getDailyRegistrationTrends();

        return [
            Stat::make('Total accounts', User::query()->toBase()->count('*'))
                ->description('All registered user accounts')
                ->color('primary')
                ->chart($dailyRegistrationTrends['registrations']),
            Stat::make('Verified accounts', User::query()->toBase()->whereNotNull('email_verified_at')->count('*'))
                ->description('Accounts with a verified email address')
                ->color('success')
                ->chart($dailyRegistrationTrends['verifiedRegistrations']),
        ];
    }

    /**
     * @return array{registrations: list<float>, verifiedRegistrations: list<float>}
     */
    private function getDailyRegistrationTrends(): array
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays(6)->startOfDay();
        $registrationCountsByDate = User::query()
            ->toBase()
            ->selectRaw('DATE(created_at) as registration_date, COUNT(*) as registration_count, SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_registration_count')
            ->whereBetween('created_at', [$start, $end], 'and')
            ->groupBy('registration_date')
            ->get();

        $registrationCounts = [];

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $registrationCounts[$start->addDays($dayOffset)->toDateString()] = 0;
        }

        $verifiedRegistrationCounts = $registrationCounts;

        foreach ($registrationCountsByDate as $registrationCountByDate) {
            $registrationDateValue = $registrationCountByDate->registration_date;
            $registrationCountValue = $registrationCountByDate->registration_count;
            $verifiedRegistrationCountValue = $registrationCountByDate->verified_registration_count;

            throw_if(! \is_string($registrationDateValue)
                || (! \is_int($registrationCountValue) && (! \is_string($registrationCountValue) || ! ctype_digit($registrationCountValue)))
                || (! \is_int($verifiedRegistrationCountValue) && (! \is_string($verifiedRegistrationCountValue) || ! ctype_digit($verifiedRegistrationCountValue))), UnexpectedValueException::class, 'The registration aggregate returned an invalid value.');

            $registrationCounts[$registrationDateValue] = (int) $registrationCountValue;
            $verifiedRegistrationCounts[$registrationDateValue] = (int) $verifiedRegistrationCountValue;
        }

        return [
            'registrations' => array_map(static fn (int $count): float => (float) $count, array_values($registrationCounts)),
            'verifiedRegistrations' => array_map(static fn (int $count): float => (float) $count, array_values($verifiedRegistrationCounts)),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs\Widgets;

use App\Modules\ActivityLog\Enums\ActivityLogName;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;
use UnexpectedValueException;

class ActivityLogOverview extends StatsOverviewWidget
{
    #[Override]
    protected int|string|array $columnSpan = 'full';

    #[Override]
    protected ?string $heading = 'Security and administration (last 7 days)';

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminActivityViewAny->value);
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $activityMetrics = $this->getActivityMetrics();

        return [
            Stat::make('Failed sign-ins', $activityMetrics['failedSignIns'])
                ->description('Authentication attempts that were rejected')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($activityMetrics['failedSignIns'] > 0 ? 'danger' : 'success'),
            Stat::make('Trusted device changes', $activityMetrics['trustedDeviceChanges'])
                ->description('Devices added, renamed, renewed, revoked or reactivated')
                ->descriptionIcon(Heroicon::OutlinedDevicePhoneMobile)
                ->color('info'),
            Stat::make('Admin actions', $activityMetrics['adminActions'])
                ->description('Roles, permissions, invitations, accounts and backups')
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color('warning'),
        ];
    }

    /**
     * @return array{failedSignIns: int, trustedDeviceChanges: int, adminActions: int}
     */
    private function getActivityMetrics(): array
    {
        $now = CarbonImmutable::now();
        $start = $now->startOfDay()->subDays(6);
        $end = $now->endOfDay();

        $activityMetrics = ActivityLog::query()
            ->toBase()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(CASE WHEN event = ? THEN 1 ELSE 0 END), 0) as failed_sign_ins', [ActivityLogAuthEvent::LoginFailed->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN log_name = ? THEN 1 ELSE 0 END), 0) as trusted_device_changes', [ActivityLogName::TrustedDevices->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN log_name = ? THEN 1 ELSE 0 END), 0) as admin_actions', [ActivityLogName::Admin->value])
            ->first();

        throw_if($activityMetrics === null, UnexpectedValueException::class, 'The activity metrics aggregate returned no result.');

        return [
            'failedSignIns' => $this->aggregateCount($activityMetrics->failed_sign_ins),
            'trustedDeviceChanges' => $this->aggregateCount($activityMetrics->trusted_device_changes),
            'adminActions' => $this->aggregateCount($activityMetrics->admin_actions),
        ];
    }

    private function aggregateCount(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException('The activity metrics aggregate returned an invalid count.');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Data\BackupListingData;
use App\Modules\Admin\Data\DashboardBackupSummaryData;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Services\BackupManagementService;
use App\Modules\Admin\Services\DashboardBackupSummaryCache;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Override;
use Throwable;

class OperationalSummaryWidget extends StatsOverviewWidget
{
    #[Override]
    protected ?string $heading = 'Operational summary';

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && (
                $authenticatedUser->can(AdminPermission::AdminUsersViewAny->value)
                || $authenticatedUser->can(AdminPermission::AdminActivityViewAny->value)
                || $authenticatedUser->can(AdminPermission::AdminBackupsView->value)
            );
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $authenticatedUser = auth('admin')->user();

        if (! $authenticatedUser instanceof User) {
            return [];
        }

        $stats = [];

        if ($authenticatedUser->can(AdminPermission::AdminUsersViewAny->value)) {
            $usersUrl = null;

            try {
                $usersUrl = UserResource::getUrl('index', panel: 'admin');
                $accountStatusSummary = $this->accountStatusSummary();

                $stats[] = Stat::make(
                    'Account status',
                    "{$accountStatusSummary['active_accounts']} active · {$accountStatusSummary['suspended_accounts']} suspended",
                )
                    ->description("{$accountStatusSummary['recent_status_changes']} status changes in the last 7 days")
                    ->color('primary')
                    ->url($usersUrl);
            } catch (Throwable $throwable) {
                report($throwable);

                $stats[] = Stat::make('Account status', 'Unavailable')
                    ->description('Account metrics could not be loaded')
                    ->color('warning')
                    ->url($usersUrl);
            }
        }

        if ($authenticatedUser->can(AdminPermission::AdminActivityViewAny->value)) {
            $activityLogUrl = null;

            try {
                $activityLogUrl = ActivityLogResource::getUrl('index', panel: 'admin');
                $recentActivityCount = $this->recentActivityCount();

                $stats[] = Stat::make('Activity (7 days)', $recentActivityCount)
                    ->description('Recorded events in the activity log')
                    ->color('primary')
                    ->url($activityLogUrl);
            } catch (Throwable $throwable) {
                report($throwable);

                $stats[] = Stat::make('Activity (7 days)', 'Unavailable')
                    ->description('Activity metrics could not be loaded')
                    ->color('warning')
                    ->url($activityLogUrl);
            }
        }

        if ($authenticatedUser->can(AdminPermission::AdminBackupsView->value)) {
            $backupsUrl = null;

            try {
                $backupsUrl = BackupManagement::getUrl(panel: 'admin');
                $backupSummary = $this->backupSummary($authenticatedUser);

                $stats[] = Stat::make('Latest backup', $this->formatLatestBackupAge($backupSummary->latestCreatedAt))
                    ->description($this->formatBackupDescription($backupSummary))
                    ->color($this->backupSummaryColor($backupSummary))
                    ->url($backupsUrl);
            } catch (Throwable $throwable) {
                report($throwable);

                $stats[] = Stat::make('Latest backup', 'Unavailable')
                    ->description('Backup summary could not be loaded')
                    ->color('warning')
                    ->url($backupsUrl);
            }
        }

        return $stats;
    }

    /**
     * @return array{active_accounts: int, suspended_accounts: int, recent_status_changes: int}
     */
    private function accountStatusSummary(): array
    {
        $activeAccounts = User::query()
            ->toBase()
            ->where('status', AccountStatus::Active->value)
            ->count();
        $suspendedAccounts = User::query()
            ->toBase()
            ->where('status', AccountStatus::Suspended->value)
            ->count();

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays(6)->startOfDay();
        $recentStatusChanges = AccountStatusChange::query()
            ->toBase()
            ->whereBetween('created_at', [$start, $end], 'and')
            ->count();

        return [
            'active_accounts' => $activeAccounts,
            'suspended_accounts' => $suspendedAccounts,
            'recent_status_changes' => $recentStatusChanges,
        ];
    }

    private function recentActivityCount(): int
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays(6)->startOfDay();

        return ActivityLog::query()
            ->toBase()
            ->whereBetween('created_at', [$start, $end], 'and')
            ->count();
    }

    private function backupSummary(User $authenticatedUser): DashboardBackupSummaryData
    {
        return Cache::remember(
            DashboardBackupSummaryCache::KEY,
            now()->addMinutes(DashboardBackupSummaryCache::TTL_MINUTES),
            function () use ($authenticatedUser): DashboardBackupSummaryData {
                $backupListingData = resolve(BackupManagementService::class)->listBackups($authenticatedUser);

                return $this->summarizeBackups($backupListingData);
            },
        );
    }

    private function summarizeBackups(BackupListingData $backupListingData): DashboardBackupSummaryData
    {
        $availableCount = 0;
        $latestBackupTimestamp = null;
        $latestBackupCreatedAt = null;

        foreach ($backupListingData->backups as $backup) {
            if (! $backup['available']) {
                continue;
            }

            $availableCount++;

            if ($backup['created_at'] === null) {
                continue;
            }

            $createdAt = CarbonImmutable::parse($backup['created_at']);

            if (! $latestBackupTimestamp instanceof CarbonImmutable || $createdAt->greaterThan($latestBackupTimestamp)) {
                $latestBackupTimestamp = $createdAt;
                $latestBackupCreatedAt = $createdAt->toIso8601String();
            }
        }

        return new DashboardBackupSummaryData(
            availableCount: $availableCount,
            totalCount: \count($backupListingData->backups),
            latestCreatedAt: $latestBackupCreatedAt,
            healthy: $backupListingData->health['healthy'],
        );
    }

    private function formatLatestBackupAge(?string $latestBackupCreatedAt): string
    {
        return $latestBackupCreatedAt === null
            ? 'Unavailable'
            : CarbonImmutable::parse($latestBackupCreatedAt)->diffForHumans();
    }

    private function formatBackupDescription(DashboardBackupSummaryData $backupSummary): string
    {
        $availability = $backupSummary->totalCount === 0
            ? 'No backups found · 0/0 available'
            : "{$backupSummary->availableCount}/{$backupSummary->totalCount} available";

        $destinationHealth = $backupSummary->healthy ? 'Destination healthy' : 'Destination needs attention';

        return "{$availability} · {$destinationHealth}";
    }

    private function backupSummaryColor(DashboardBackupSummaryData $backupSummary): string
    {
        return $backupSummary->healthy
            && $backupSummary->availableCount === $backupSummary->totalCount
            && $backupSummary->totalCount > 0
                ? 'success'
                : 'warning';
    }
}

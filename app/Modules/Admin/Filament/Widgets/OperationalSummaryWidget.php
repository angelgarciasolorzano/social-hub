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
use App\Support\Translation\TranslatedText;
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
    protected ?string $pollingInterval = null;

    #[Override]
    protected function getHeading(): string
    {
        return TranslatedText::of('admin/dashboard.operational_summary.heading');
    }

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
                    TranslatedText::of('admin/dashboard.operational_summary.account_status.label'),
                    trans_choice('admin/dashboard.operational_summary.account_status.active', $accountStatusSummary['active_accounts'])
                        .' · '.trans_choice('admin/dashboard.operational_summary.account_status.suspended', $accountStatusSummary['suspended_accounts']),
                )
                    ->description(trans_choice('admin/dashboard.operational_summary.account_status.recent_changes', $accountStatusSummary['recent_status_changes']))
                    ->color('primary')
                    ->url($usersUrl);
            } catch (Throwable $throwable) {
                report($throwable);

                $stats[] = Stat::make(TranslatedText::of('admin/dashboard.operational_summary.account_status.label'), TranslatedText::of('admin/common.unavailable'))
                    ->description(TranslatedText::of('admin/dashboard.operational_summary.account_status.unavailable'))
                    ->color('warning')
                    ->url($usersUrl);
            }
        }

        if ($authenticatedUser->can(AdminPermission::AdminActivityViewAny->value)) {
            $activityLogUrl = null;

            try {
                $activityLogUrl = ActivityLogResource::getUrl('index', panel: 'admin');
                $recentActivityCount = $this->recentActivityCount();

                $stats[] = Stat::make(TranslatedText::of('admin/dashboard.operational_summary.activity.label'), $recentActivityCount)
                    ->description(TranslatedText::of('admin/dashboard.operational_summary.activity.description'))
                    ->color('primary')
                    ->url($activityLogUrl);
            } catch (Throwable $throwable) {
                report($throwable);

                $stats[] = Stat::make(TranslatedText::of('admin/dashboard.operational_summary.activity.label'), TranslatedText::of('admin/common.unavailable'))
                    ->description(TranslatedText::of('admin/dashboard.operational_summary.activity.unavailable'))
                    ->color('warning')
                    ->url($activityLogUrl);
            }
        }

        if ($authenticatedUser->can(AdminPermission::AdminBackupsView->value)) {
            $backupsUrl = null;

            try {
                $backupsUrl = BackupManagement::getUrl(panel: 'admin');
                $backupSummary = $this->backupSummary($authenticatedUser);

                $stats[] = Stat::make(TranslatedText::of('admin/dashboard.operational_summary.backups.label'), $this->formatLatestBackupAge($backupSummary->latestCreatedAt))
                    ->description($this->formatBackupDescription($backupSummary))
                    ->color($this->backupSummaryColor($backupSummary))
                    ->url($backupsUrl);
            } catch (Throwable $throwable) {
                report($throwable);

                $stats[] = Stat::make(TranslatedText::of('admin/dashboard.operational_summary.backups.label'), TranslatedText::of('admin/common.unavailable'))
                    ->description(TranslatedText::of('admin/dashboard.operational_summary.backups.unavailable'))
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
            ? TranslatedText::of('admin/common.unavailable')
            : CarbonImmutable::parse($latestBackupCreatedAt)->diffForHumans();
    }

    private function formatBackupDescription(DashboardBackupSummaryData $backupSummary): string
    {
        $availability = $backupSummary->totalCount === 0
            ? TranslatedText::of('admin/dashboard.operational_summary.backups.none_found')
            : trans_choice('admin/dashboard.operational_summary.backups.available', $backupSummary->availableCount, [
                'available' => $backupSummary->availableCount,
                'total' => $backupSummary->totalCount,
            ]);

        $destinationHealth = TranslatedText::of($backupSummary->healthy
            ? 'admin/dashboard.operational_summary.backups.destination_healthy'
            : 'admin/dashboard.operational_summary.backups.destination_needs_attention');

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

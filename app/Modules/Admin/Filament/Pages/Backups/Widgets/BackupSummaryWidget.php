<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages\Backups\Widgets;

use App\Modules\Admin\Data\BackupSummaryData;
use App\Modules\Admin\Enums\AdminPermission;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Livewire\Attributes\Locked;
use Override;

class BackupSummaryWidget extends StatsOverviewWidget
{
    #[Override]
    protected static bool $isLazy = false;

    /**
     * @var array{
     *     backups: list<array{filename: string, created_at: string|null, size_in_bytes: float|null, disk: string, available: bool}>,
     *     health: array{healthy: bool, failed_checks: list<string>}
     * }|null
     */
    #[Locked]
    public ?array $backupListingData = null;

    #[Override]
    protected ?string $heading = 'Backup summary';

    #[Override]
    protected ?string $pollingInterval = null;

    private ?BackupSummaryData $summary = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminBackupsView->value);
    }

    #[Override]
    protected function getDescription(): ?string
    {
        $backupSummaryData = $this->getSummary();

        if (! $backupSummaryData->listingAvailable) {
            return 'Backup listing is unavailable.';
        }

        $destinationHealth = $backupSummaryData->destinationHealthy
            ? 'Destination healthy.'
            : 'Destination needs attention.';

        return $backupSummaryData->hasBackups
            ? $destinationHealth
            : $destinationHealth.' No backups found.';
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $backupSummaryData = $this->getSummary();

        return [
            Stat::make('Available backups', $backupSummaryData->availableCount)
                ->description('Archives present in storage')
                ->color('success'),
            Stat::make('Missing backups', $backupSummaryData->missingCount)
                ->description($backupSummaryData->missingCount === 0 ? 'No missing archives' : 'Archives not found in storage')
                ->color($backupSummaryData->missingCount === 0 ? 'success' : 'warning'),
            Stat::make('Latest available backup', $backupSummaryData->latestBackupAge ?? 'Unavailable')
                ->description($backupSummaryData->latestBackupCreatedAt ?? 'No available backups'),
            Stat::make(
                'Total backup size',
                $backupSummaryData->totalSizeInBytes === null
                    ? 'Unavailable'
                    : Number::fileSize($backupSummaryData->totalSizeInBytes, maxPrecision: 2),
            )->description($backupSummaryData->totalSizeInBytes === null
                ? 'One or more archive sizes are unavailable'
                : ($backupSummaryData->hasBackups ? 'Combined size of listed archives' : 'No backups stored')),
        ];
    }

    private function getSummary(): BackupSummaryData
    {
        if ($this->summary instanceof BackupSummaryData) {
            return $this->summary;
        }

        $backupListingData = $this->backupListingData;

        if (! \is_array($backupListingData)) {
            return $this->summary = new BackupSummaryData(
                listingAvailable: false,
                availableCount: 0,
                missingCount: 0,
                hasBackups: false,
                destinationHealthy: false,
                latestBackupAge: null,
                latestBackupCreatedAt: null,
                totalSizeInBytes: null,
            );
        }

        $availableCount = 0;
        $latestBackupDate = null;
        $totalSizeInBytes = 0.0;
        $allSizesAvailable = true;

        foreach ($backupListingData['backups'] as $backup) {
            if ($backup['available']) {
                $availableCount++;

                if ($backup['created_at'] !== null) {
                    $backupDate = CarbonImmutable::parse($backup['created_at']);

                    if (! $latestBackupDate instanceof CarbonImmutable || $backupDate->greaterThan($latestBackupDate)) {
                        $latestBackupDate = $backupDate;
                    }
                }
            }

            if ($backup['size_in_bytes'] === null) {
                $allSizesAvailable = false;
            } else {
                $totalSizeInBytes += $backup['size_in_bytes'];
            }
        }

        $missingCount = \count($backupListingData['backups']) - $availableCount;

        return $this->summary = new BackupSummaryData(
            listingAvailable: true,
            availableCount: $availableCount,
            missingCount: $missingCount,
            hasBackups: $backupListingData['backups'] !== [],
            destinationHealthy: $backupListingData['health']['healthy'],
            latestBackupAge: $latestBackupDate?->diffForHumans(),
            latestBackupCreatedAt: $latestBackupDate?->format('M j, Y H:i'),
            totalSizeInBytes: $allSizesAvailable ? $totalSizeInBytes : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages\Backups\Widgets;

use App\Modules\Admin\Data\BackupSummaryData;
use App\Modules\Admin\Enums\AdminPermission;
use App\Support\Translation\TranslatedText;
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
    protected function getHeading(): string
    {
        return TranslatedText::of('admin/backups.widget.heading');
    }

    #[Override]
    protected function getDescription(): ?string
    {
        $backupSummaryData = $this->getSummary();

        if (! $backupSummaryData->listingAvailable) {
            return TranslatedText::of('admin/backups.widget.listing_unavailable');
        }

        $destinationHealth = TranslatedText::of($backupSummaryData->destinationHealthy
            ? 'admin/backups.widget.destination_healthy'
            : 'admin/backups.widget.destination_needs_attention');

        return $backupSummaryData->hasBackups
            ? $destinationHealth
            : $destinationHealth.' '.TranslatedText::of('admin/backups.widget.no_backups_found');
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $backupSummaryData = $this->getSummary();

        return [
            Stat::make(TranslatedText::of('admin/backups.widget.available.label'), $backupSummaryData->availableCount)
                ->description(TranslatedText::of('admin/backups.widget.available.description'))
                ->color('success'),
            Stat::make(TranslatedText::of('admin/backups.widget.missing.label'), $backupSummaryData->missingCount)
                ->description(TranslatedText::of($backupSummaryData->missingCount === 0 ? 'admin/backups.widget.missing.none' : 'admin/backups.widget.missing.description'))
                ->color($backupSummaryData->missingCount === 0 ? 'success' : 'warning'),
            Stat::make(TranslatedText::of('admin/backups.widget.latest.label'), $backupSummaryData->latestBackupAge ?? TranslatedText::of('admin/common.unavailable'))
                ->description($backupSummaryData->latestBackupCreatedAt ?? TranslatedText::of('admin/backups.widget.latest.none')),
            Stat::make(
                TranslatedText::of('admin/backups.widget.total_size.label'),
                $backupSummaryData->totalSizeInBytes === null
                    ? TranslatedText::of('admin/common.unavailable')
                    : Number::fileSize($backupSummaryData->totalSizeInBytes, maxPrecision: 2),
            )->description(TranslatedText::of(match (true) {
                $backupSummaryData->totalSizeInBytes === null => 'admin/backups.widget.total_size.sizes_unavailable',
                $backupSummaryData->hasBackups => 'admin/backups.widget.total_size.description',
                default => 'admin/backups.widget.total_size.none',
            })),
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
            latestBackupCreatedAt: $latestBackupDate?->translatedFormat(TranslatedText::of('admin/backups.widget.latest.date_format')),
            totalSizeInBytes: $allSizesAvailable ? $totalSizeInBytes : null,
        );
    }
}

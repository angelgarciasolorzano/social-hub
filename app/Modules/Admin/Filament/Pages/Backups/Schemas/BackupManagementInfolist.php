<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages\Backups\Schemas;

use App\Modules\Admin\Data\BackupListingData;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BackupManagementInfolist
{
    public static function configure(Schema $schema, BackupListingData $backupListingData): Schema
    {
        $destination = $backupListingData->destination;
        $retention = $destination->retention;

        return $schema
            ->constantState([
                'health_status' => $backupListingData->health['healthy'] ? 'Healthy' : 'Unhealthy',
                'health_message' => $backupListingData->health['healthy']
                    ? 'All configured backup health checks passed.'
                    : 'One or more configured backup health checks failed.',
                'failed_checks' => implode(', ', array_map(
                    static fn (string $failedCheck): string => (string) str($failedCheck)->headline(),
                    $backupListingData->health['failed_checks'],
                )),
                'provider' => $destination->provider,
                'bucket' => $destination->bucket ?? 'Not configured',
                'prefix' => $destination->prefix,
                'region' => $destination->region ?? 'Not configured',
                'visibility' => str($destination->visibility)->headline(),
                'archive_encryption' => $destination->archiveEncryption ?? 'Disabled',
                'all_backups_retention' => $retention['keep_all_backups_for_days'] > 0
                    ? \sprintf('%d days', $retention['keep_all_backups_for_days'])
                    : 'Disabled',
                'daily_snapshots_retention' => $retention['keep_daily_backups_for_days'] > 0
                    ? \sprintf('%d days', $retention['keep_daily_backups_for_days'])
                    : 'Disabled',
                'weekly_snapshots_retention' => $retention['keep_weekly_backups_for_weeks'] > 0
                    ? \sprintf('%d weeks', $retention['keep_weekly_backups_for_weeks'])
                    : 'Disabled',
                'monthly_snapshots_retention' => $retention['keep_monthly_backups_for_months'] > 0
                    ? \sprintf('%d months', $retention['keep_monthly_backups_for_months'])
                    : 'Disabled',
                'yearly_snapshots_retention' => $retention['keep_yearly_backups_for_years'] > 0
                    ? \sprintf('%d years', $retention['keep_yearly_backups_for_years'])
                    : 'Disabled',
            ])
            ->components([
                Section::make('Backup destination')
                    ->columnSpan(['xl' => 2])
                    ->description('Storage target, availability, and protection for generated backup archives.')
                    ->icon(Heroicon::OutlinedCloudArrowUp)
                    ->schema([
                        TextEntry::make('health_status')
                            ->label('Health')
                            ->badge()
                            ->icon(fn (string $state): Heroicon => match ($state) {
                                'Healthy' => Heroicon::OutlinedCheckCircle,
                                default => Heroicon::OutlinedExclamationTriangle,
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'Healthy' => 'success',
                                default => 'danger',
                            }),
                        TextEntry::make('health_message')
                            ->label('Health details')
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->columnSpan(['default' => 1, 'xl' => 2]),
                        TextEntry::make('failed_checks')
                            ->label('Failed checks')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->iconColor('danger')
                            ->color('danger')
                            ->visible(fn (mixed $state): bool => \is_string($state) && $state !== '')
                            ->columnSpanFull(),
                        TextEntry::make('provider')
                            ->label('Storage provider')
                            ->icon(Heroicon::OutlinedCloud),
                        TextEntry::make('bucket')
                            ->label('Bucket')
                            ->icon(Heroicon::OutlinedCircleStack),
                        TextEntry::make('prefix')
                            ->label('Backup prefix')
                            ->icon(Heroicon::OutlinedFolder),
                        TextEntry::make('region')
                            ->label('Region')
                            ->icon(Heroicon::OutlinedMapPin),
                        TextEntry::make('visibility')
                            ->label('Access')
                            ->icon(Heroicon::OutlinedLockClosed),
                        TextEntry::make('archive_encryption')
                            ->label('Archive encryption')
                            ->icon(Heroicon::OutlinedShieldCheck),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 3]),
                Section::make('Retention policy')
                    ->columnSpan(['xl' => 1])
                    ->description('Automated cleanup schedule for backup archives.')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('all_backups_retention')
                            ->label('All backups')
                            ->icon(Heroicon::OutlinedArchiveBox),
                        TextEntry::make('daily_snapshots_retention')
                            ->label('Daily snapshots')
                            ->icon(Heroicon::OutlinedCalendar),
                        TextEntry::make('weekly_snapshots_retention')
                            ->label('Weekly snapshots')
                            ->icon(Heroicon::OutlinedCalendarDays),
                        TextEntry::make('monthly_snapshots_retention')
                            ->label('Monthly snapshots')
                            ->icon(Heroicon::OutlinedCalendarDateRange),
                        TextEntry::make('yearly_snapshots_retention')
                            ->label('Yearly snapshots')
                            ->icon(Heroicon::OutlinedClock),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 1]),
            ])
            ->columns(['default' => 1, 'xl' => 3]);
    }
}

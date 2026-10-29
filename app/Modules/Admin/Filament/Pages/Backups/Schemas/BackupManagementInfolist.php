<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages\Backups\Schemas;

use App\Modules\Admin\Data\BackupDestinationData;
use App\Modules\Admin\Data\BackupListingData;
use App\Support\Translation\TranslatedText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Lang;

class BackupManagementInfolist
{
    public static function configure(Schema $schema, BackupListingData $backupListingData): Schema
    {
        $destination = $backupListingData->destination;
        $isHealthy = $backupListingData->health['healthy'];

        return $schema
            ->constantState([
                ...self::healthState($backupListingData->health),
                ...self::destinationState($destination),
                ...self::retentionState($destination->retention),
            ])
            ->components([
                Section::make(TranslatedText::of('admin/backups.destination.heading'))
                    ->columnSpan(['xl' => 2])
                    ->description(TranslatedText::of('admin/backups.destination.description'))
                    ->icon(Heroicon::OutlinedCloudArrowUp)
                    ->schema([
                        TextEntry::make('health_status')
                            ->label(TranslatedText::of('admin/backups.destination.health'))
                            ->badge()
                            ->icon($isHealthy ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
                            ->color($isHealthy ? 'success' : 'danger'),
                        TextEntry::make('health_message')
                            ->label(TranslatedText::of('admin/backups.destination.health_details'))
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->columnSpan(['default' => 1, 'xl' => 2]),
                        TextEntry::make('failed_checks')
                            ->label(TranslatedText::of('admin/backups.destination.failed_checks'))
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->iconColor('danger')
                            ->color('danger')
                            ->visible(fn (mixed $state): bool => \is_string($state) && $state !== '')
                            ->columnSpanFull(),
                        TextEntry::make('provider')
                            ->label(TranslatedText::of('admin/backups.destination.provider'))
                            ->icon(Heroicon::OutlinedCloud),
                        TextEntry::make('bucket')
                            ->label(TranslatedText::of('admin/backups.destination.bucket'))
                            ->icon(Heroicon::OutlinedCircleStack),
                        TextEntry::make('prefix')
                            ->label(TranslatedText::of('admin/backups.destination.prefix'))
                            ->icon(Heroicon::OutlinedFolder),
                        TextEntry::make('region')
                            ->label(TranslatedText::of('admin/backups.destination.region'))
                            ->icon(Heroicon::OutlinedMapPin),
                        TextEntry::make('visibility')
                            ->label(TranslatedText::of('admin/backups.destination.access'))
                            ->icon(Heroicon::OutlinedLockClosed),
                        TextEntry::make('archive_encryption')
                            ->label(TranslatedText::of('admin/backups.destination.archive_encryption'))
                            ->icon(Heroicon::OutlinedShieldCheck),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 3]),
                Section::make(TranslatedText::of('admin/backups.retention.heading'))
                    ->columnSpan(['xl' => 1])
                    ->description(TranslatedText::of('admin/backups.retention.description'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('all_backups_retention')
                            ->label(TranslatedText::of('admin/backups.retention.all_backups'))
                            ->icon(Heroicon::OutlinedArchiveBox),
                        TextEntry::make('daily_snapshots_retention')
                            ->label(TranslatedText::of('admin/backups.retention.daily_snapshots'))
                            ->icon(Heroicon::OutlinedCalendar),
                        TextEntry::make('weekly_snapshots_retention')
                            ->label(TranslatedText::of('admin/backups.retention.weekly_snapshots'))
                            ->icon(Heroicon::OutlinedCalendarDays),
                        TextEntry::make('monthly_snapshots_retention')
                            ->label(TranslatedText::of('admin/backups.retention.monthly_snapshots'))
                            ->icon(Heroicon::OutlinedCalendarDateRange),
                        TextEntry::make('yearly_snapshots_retention')
                            ->label(TranslatedText::of('admin/backups.retention.yearly_snapshots'))
                            ->icon(Heroicon::OutlinedClock),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 1]),
            ])
            ->columns(['default' => 1, 'xl' => 3]);
    }

    /**
     * @param  array{healthy: bool, failed_checks: list<string>}  $health
     * @return array{health_status: string, health_message: string, failed_checks: string}
     */
    private static function healthState(array $health): array
    {
        return [
            'health_status' => TranslatedText::of($health['healthy'] ? 'admin/backups.destination.healthy' : 'admin/backups.destination.unhealthy'),
            'health_message' => TranslatedText::of($health['healthy']
                ? 'admin/backups.destination.all_checks_passed'
                : 'admin/backups.destination.checks_failed'),
            'failed_checks' => implode(', ', array_map(
                static fn (string $failedCheck): string => (string) str($failedCheck)->headline(),
                $health['failed_checks'],
            )),
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     bucket: string,
     *     prefix: string,
     *     region: string,
     *     visibility: string,
     *     archive_encryption: string
     * }
     */
    private static function destinationState(BackupDestinationData $destination): array
    {
        return [
            'provider' => $destination->provider,
            'bucket' => $destination->bucket ?? TranslatedText::of('admin/backups.destination.not_configured'),
            'prefix' => $destination->prefix,
            'region' => $destination->region ?? TranslatedText::of('admin/backups.destination.not_configured'),
            'visibility' => self::visibilityLabel($destination->visibility),
            'archive_encryption' => $destination->archiveEncryption ?? TranslatedText::of('admin/backups.disabled'),
        ];
    }

    /**
     * @param  array{
     *     keep_all_backups_for_days: int,
     *     keep_daily_backups_for_days: int,
     *     keep_weekly_backups_for_weeks: int,
     *     keep_monthly_backups_for_months: int,
     *     keep_yearly_backups_for_years: int
     * }  $retention
     * @return array{
     *     all_backups_retention: string,
     *     daily_snapshots_retention: string,
     *     weekly_snapshots_retention: string,
     *     monthly_snapshots_retention: string,
     *     yearly_snapshots_retention: string
     * }
     */
    private static function retentionState(array $retention): array
    {
        return [
            'all_backups_retention' => self::formatRetentionPeriod($retention['keep_all_backups_for_days'], 'days'),
            'daily_snapshots_retention' => self::formatRetentionPeriod($retention['keep_daily_backups_for_days'], 'days'),
            'weekly_snapshots_retention' => self::formatRetentionPeriod($retention['keep_weekly_backups_for_weeks'], 'weeks'),
            'monthly_snapshots_retention' => self::formatRetentionPeriod($retention['keep_monthly_backups_for_months'], 'months'),
            'yearly_snapshots_retention' => self::formatRetentionPeriod($retention['keep_yearly_backups_for_years'], 'years'),
        ];
    }

    /**
     * @param  'days'|'weeks'|'months'|'years'  $unit
     */
    private static function formatRetentionPeriod(int $duration, string $unit): string
    {
        return $duration > 0
            ? trans_choice('admin/backups.retention.'.$unit, $duration)
            : TranslatedText::of('admin/backups.disabled');
    }

    /**
     * The disk visibility in the current language, or its headline for a value without a translation.
     */
    private static function visibilityLabel(string $visibility): string
    {
        $key = 'admin/backups.destination.visibilities.'.$visibility;

        return Lang::has($key) ? TranslatedText::of($key) : (string) str($visibility)->headline();
    }
}

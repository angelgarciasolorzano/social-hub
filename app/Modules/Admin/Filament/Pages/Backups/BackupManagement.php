<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages\Backups;

use App\Modules\Admin\Data\BackupListingData;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Pages\Backups\Tables\BackupsTable;
use App\Modules\Admin\Services\BackupManagementService;
use App\User\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class BackupManagement extends Page implements HasTable
{
    use InteractsWithTable;

    #[Override]
    protected static ?string $title = 'Backup management';

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    #[Override]
    protected static ?string $navigationLabel = 'Backups';

    #[Override]
    protected string $view = 'admin::filament.backups.backup-management';

    private ?BackupListingData $backupListing = null;

    public static function canAccess(): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminBackupsView->value);
    }

    /**
     * @return array<Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBackup')
                ->label('Create backup')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->requiresConfirmation()
                ->modalHeading('Create a database backup?')
                ->modalDescription('The backup will be queued and appear in this list after the queue finishes processing it.')
                ->modalSubmitActionLabel('Queue backup')
                ->successNotificationTitle('Database backup queued')
                ->authorize(fn (): bool => $this->hasPermission(AdminPermission::AdminBackupsCreate))
                ->action(function (): void {
                    $this->createBackup();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return BackupsTable::configure($table, $this);
    }

    public function backupDestinationInfolist(Schema $schema): Schema
    {
        $backupListingData = $this->getBackupListing();
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
                    ? sprintf('%d days', $retention['keep_all_backups_for_days'])
                    : 'Disabled',
                'daily_snapshots_retention' => $retention['keep_daily_backups_for_days'] > 0
                    ? sprintf('%d days', $retention['keep_daily_backups_for_days'])
                    : 'Disabled',
                'weekly_snapshots_retention' => $retention['keep_weekly_backups_for_weeks'] > 0
                    ? sprintf('%d weeks', $retention['keep_weekly_backups_for_weeks'])
                    : 'Disabled',
                'monthly_snapshots_retention' => $retention['keep_monthly_backups_for_months'] > 0
                    ? sprintf('%d months', $retention['keep_monthly_backups_for_months'])
                    : 'Disabled',
                'yearly_snapshots_retention' => $retention['keep_yearly_backups_for_years'] > 0
                    ? sprintf('%d years', $retention['keep_yearly_backups_for_years'])
                    : 'Disabled',
            ])
            ->components([
                Section::make('Backup destination')
                    ->schema([
                        TextEntry::make('health_status')
                            ->label('Health')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Healthy' => 'success',
                                default => 'danger',
                            }),
                        TextEntry::make('health_message')
                            ->label('Health details')
                            ->columnSpan(['default' => 1, 'xl' => 2]),
                        TextEntry::make('failed_checks')
                            ->label('Failed checks')
                            ->color('danger')
                            ->visible(fn (mixed $state): bool => is_string($state) && $state !== '')
                            ->columnSpanFull(),
                        TextEntry::make('provider')
                            ->label('Storage provider'),
                        TextEntry::make('bucket')
                            ->label('Bucket'),
                        TextEntry::make('prefix')
                            ->label('Backup prefix'),
                        TextEntry::make('region')
                            ->label('Region'),
                        TextEntry::make('visibility')
                            ->label('Access'),
                        TextEntry::make('archive_encryption')
                            ->label('Archive encryption'),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 3]),
                Section::make('Retention policy')
                    ->schema([
                        TextEntry::make('all_backups_retention')
                            ->label('All backups'),
                        TextEntry::make('daily_snapshots_retention')
                            ->label('Daily snapshots'),
                        TextEntry::make('weekly_snapshots_retention')
                            ->label('Weekly snapshots'),
                        TextEntry::make('monthly_snapshots_retention')
                            ->label('Monthly snapshots'),
                        TextEntry::make('yearly_snapshots_retention')
                            ->label('Yearly snapshots'),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 5]),
            ]);
    }

    public function createBackup(): void
    {
        resolve(BackupManagementService::class)->queueBackup($this->authenticatedUser());
    }

    public function downloadBackup(string $filename, string $currentPassword): void
    {
        $temporaryUrl = resolve(BackupManagementService::class)->downloadBackup(
            $this->authenticatedUser(),
            $filename,
            $currentPassword,
        );

        $this->redirect($temporaryUrl, navigate: false);
    }

    public function getBackupListing(): BackupListingData
    {
        return $this->backupListing ??= resolve(BackupManagementService::class)->listBackups($this->authenticatedUser());
    }

    public function hasPermission(AdminPermission $adminPermission): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can($adminPermission->value);
    }

    private function authenticatedUser(): User
    {
        $authenticatedUser = Filament::auth()->user();

        abort_unless($authenticatedUser instanceof User, 403);

        return $authenticatedUser;
    }
}

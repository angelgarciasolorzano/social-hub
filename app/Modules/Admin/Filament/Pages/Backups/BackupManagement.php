<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages\Backups;

use App\Modules\Admin\Data\BackupListingData;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Pages\Backups\Schemas\BackupManagementInfolist;
use App\Modules\Admin\Filament\Pages\Backups\Tables\BackupsTable;
use App\Modules\Admin\Filament\Pages\Backups\Widgets\BackupSummaryWidget;
use App\Modules\Admin\Services\BackupManagementService;
use App\User\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
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

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            BackupSummaryWidget::class,
        ];
    }

    /**
     * @return array{
     *     backupListingData: array{
     *         backups: list<array{filename: string, created_at: string|null, size_in_bytes: float|null, disk: string, available: bool}>,
     *         health: array{healthy: bool, failed_checks: list<string>}
     *     }
     * }
     */
    #[Override]
    public function getWidgetData(): array
    {
        $backupListingData = $this->getBackupListing();

        return [
            'backupListingData' => [
                'backups' => $backupListingData->backups,
                'health' => $backupListingData->health,
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return BackupsTable::configure($table, $this);
    }

    public function backupDestinationInfolist(Schema $schema): Schema
    {
        return BackupManagementInfolist::configure($schema, $this->getBackupListing());
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

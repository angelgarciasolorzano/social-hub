<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Services\BackupManagementService;
use App\User\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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
    protected string $view = 'admin::backups.backup-management';

    /**
     * @var array{
     *     backups: list<array{
     *         filename: string,
     *         created_at: string|null,
     *         size_in_bytes: float|null,
     *         disk: string,
     *         available: bool
     *     }>,
     *     health: array{healthy: bool, failed_checks: list<string>}
     * }|null
     */
    private ?array $backupListing = null;

    /** Show the backup archive metadata and the status of its destination. */
    public function table(Table $table): Table
    {
        return $table
            ->heading('Backups')
            ->records(fn (): Collection => collect($this->getBackupListing()['backups'])->keyBy('filename'))
            ->columns([
                TextColumn::make('filename')
                    ->label('Archive'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i'),
                TextColumn::make('size_in_bytes')
                    ->label('Size')
                    ->formatStateUsing(static fn (?float $state): string => $state === null
                        ? 'Unavailable'
                        : number_format($state, 0).' bytes'),
                TextColumn::make('disk')
                    ->label('Disk'),
                TextColumn::make('available')
                    ->label('Availability')
                    ->badge()
                    ->formatStateUsing(static fn (bool $state): string => $state ? 'Available' : 'Missing')
                    ->color(static fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->recordActions([
                Action::make('downloadBackup')
                    ->label('Download')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current password')
                            ->password()
                            ->autocomplete('current-password')
                            ->required(),
                    ])
                    ->modalHeading('Confirm your password to download this backup')
                    ->modalSubmitActionLabel('Download backup')
                    ->authorize(fn (): bool => $this->hasPermission(AdminPermission::AdminBackupsDownload))
                    ->visible(fn (array $record): bool => $record['available']
                        && $this->hasPermission(AdminPermission::AdminBackupsDownload))
                    ->action(
                        /**
                         * @param  array{filename: string, created_at: string|null, size_in_bytes: float|null, disk: string, available: bool}  $record
                         * @param  array{current_password: string}  $data
                         */
                        function (array $record, array $data): void {
                            $filename = $record['filename'] ?? null;
                            $currentPassword = $data['current_password'] ?? null;

                            if (! is_string($filename) || ! is_string($currentPassword)) {
                                throw ValidationException::withMessages([
                                    ...(! is_string($filename) ? ['filename' => __('The selected backup is no longer available.')] : []),
                                    ...(! is_string($currentPassword) ? ['current_password' => __('The current password is required.')] : []),
                                ]);
                            }

                            $this->downloadBackup($filename, $currentPassword);
                        },
                    ),
            ])
            ->paginated(false)
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('A database backup will appear here after the first run.');
    }

    /** Restrict the page route and navigation item to backup readers. */
    public static function canAccess(): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminBackupsView->value);
    }

    /** Queue a new database backup after checking the create permission. */
    public function createBackup(): void
    {
        resolve(BackupManagementService::class)->queueBackup($this->authenticatedUser());
    }

    /** Create a password-confirmed temporary URL for the selected archive. */
    public function downloadBackup(string $filename, string $currentPassword): void
    {
        $temporaryUrl = resolve(BackupManagementService::class)->downloadBackup(
            $this->authenticatedUser(),
            $filename,
            $currentPassword,
        );

        $this->redirect($temporaryUrl, navigate: false);
    }

    /**
     * Return the authorized backup listing for the current Livewire request.
     *
     * @return array{
     *     backups: list<array{
     *         filename: string,
     *         created_at: string|null,
     *         size_in_bytes: float|null,
     *         disk: string,
     *         available: bool
     *     }>,
     *     health: array{healthy: bool, failed_checks: list<string>}
     * }
     */
    public function getBackupListing(): array
    {
        return $this->backupListing ??= resolve(BackupManagementService::class)->listBackups($this->authenticatedUser());
    }

    /**
     * Provide the manual backup action only to users with create access.
     *
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

    /** Resolve the signed-in admin from the panel guard or reject the request. */
    private function authenticatedUser(): User
    {
        $authenticatedUser = Filament::auth()->user();

        abort_unless($authenticatedUser instanceof User, 403);

        return $authenticatedUser;
    }

    /** Check a backup permission for the signed-in admin. */
    private function hasPermission(AdminPermission $adminPermission): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can($adminPermission->value);
    }
}

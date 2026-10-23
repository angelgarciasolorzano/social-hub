<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages\Backups\Tables;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class BackupsTable
{
    public static function configure(Table $table, BackupManagement $backupManagementPage): Table
    {
        return $table
            ->heading('Backups')
            ->records(function (int $page, int $recordsPerPage) use ($backupManagementPage): LengthAwarePaginator {
                $backups = collect($backupManagementPage->getBackupListing()->backups)->keyBy('filename');

                return new LengthAwarePaginator(
                    $backups->forPage($page, $recordsPerPage),
                    $backups->count(),
                    $recordsPerPage,
                    $page,
                );
            })
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
                    ->authorize(fn (): bool => $backupManagementPage->hasPermission(AdminPermission::AdminBackupsDownload))
                    ->visible(fn (array $record): bool => $record['available']
                        && $backupManagementPage->hasPermission(AdminPermission::AdminBackupsDownload))
                    ->action(
                        /**
                         * @param  array{filename: string, created_at: string|null, size_in_bytes: float|null, disk: string, available: bool}  $record
                         * @param  array{current_password: string}  $data
                         */
                        function (array $record, array $data) use ($backupManagementPage): void {
                            self::handleDownload($backupManagementPage, $record, $data);
                        },
                    ),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('A database backup will appear here after the first run.');
    }

    /**
     * @param  array<array-key, mixed>  $record
     * @param  array<array-key, mixed>  $data
     */
    private static function handleDownload(BackupManagement $page, array $record, array $data): void
    {
        $filename = $record['filename'] ?? null;
        $currentPassword = $data['current_password'] ?? null;

        if (! \is_string($filename) || ! \is_string($currentPassword)) {
            throw ValidationException::withMessages([
                ...(! \is_string($filename) ? ['filename' => __('The selected backup is no longer available.')] : []),
                ...(! \is_string($currentPassword) ? ['current_password' => __('The current password is required.')] : []),
            ]);
        }

        $page->downloadBackup($filename, $currentPassword);
    }
}

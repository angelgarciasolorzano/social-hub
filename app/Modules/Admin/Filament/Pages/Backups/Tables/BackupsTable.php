<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages\Backups\Tables;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use App\Support\Translation\TranslatedText;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class BackupsTable
{
    public static function configure(Table $table, BackupManagement $backupManagementPage): Table
    {
        return $table
            ->heading(TranslatedText::of('admin/backups.table.heading'))
            ->deferLoading()
            ->loadingSkeleton()
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
                    ->label(TranslatedText::of('admin/backups.table.archive')),
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/backups.table.created'))
                    ->dateTime('M j, Y H:i'),
                TextColumn::make('size_in_bytes')
                    ->label(TranslatedText::of('admin/backups.table.size'))
                    ->formatStateUsing(static fn (?float $state): string => $state === null
                        ? TranslatedText::of('admin/common.unavailable')
                        : Number::fileSize($state, maxPrecision: 2)),
                TextColumn::make('available')
                    ->label(TranslatedText::of('admin/backups.table.availability'))
                    ->badge()
                    ->formatStateUsing(static fn (bool $state): string => TranslatedText::of($state ? 'admin/backups.table.available' : 'admin/backups.table.missing'))
                    ->color(static fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->recordActions([
                Action::make('downloadBackup')
                    ->label(TranslatedText::of('admin/backups.actions.download.label'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(TranslatedText::of('admin/backups.actions.download.current_password'))
                            ->password()
                            ->autocomplete('current-password')
                            ->required(),
                    ])
                    ->modalHeading(TranslatedText::of('admin/backups.actions.download.modal_heading'))
                    ->modalSubmitActionLabel(TranslatedText::of('admin/backups.actions.download.submit'))
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
            ->emptyStateHeading(TranslatedText::of('admin/backups.table.empty_heading'))
            ->emptyStateDescription(TranslatedText::of('admin/backups.table.empty_description'));
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
                ...(! \is_string($filename) ? ['filename' => TranslatedText::of('admin/backups.errors.backup_unavailable')] : []),
                ...(! \is_string($currentPassword) ? ['current_password' => TranslatedText::of('admin/backups.errors.password_required')] : []),
            ]);
        }

        $page->downloadBackup($filename, $currentPassword);
    }
}

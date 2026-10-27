<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Tables;

use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Services\AnnouncementService;
use App\User\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('creator:id,name'))
            ->deferLoading()
            ->loadingSkeleton()
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (AnnouncementType $state): string => $state->label())
                    ->color(fn (AnnouncementType $state): string => self::typeColor($state)),
                TextColumn::make('audience')
                    ->label('Audience')
                    ->formatStateUsing(fn (AnnouncementAudience $state): string => $state->label()),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->placeholder('No end date')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Created by')
                    ->placeholder('Deleted user'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(self::typeOptions()),
                SelectFilter::make('audience')
                    ->label('Audience')
                    ->options(self::audienceOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    self::previewAction(),
                    self::duplicateAction(),
                    self::unpublishAction(),
                ]),
            ]);
    }

    /**
     * The badge color of each announcement type, matching how severe it is.
     */
    public static function typeColor(AnnouncementType $announcementType): string
    {
        return match ($announcementType) {
            AnnouncementType::Info => 'info',
            AnnouncementType::Warning => 'warning',
            AnnouncementType::Critical => 'danger',
        };
    }

    /**
     * Show the banner as users would see it, with its body sanitized.
     */
    private static function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::OutlinedEye)
            ->authorize('view')
            ->modalHeading('Banner preview')
            ->modalContent(fn (Announcement $record): View => view('admin::filament.announcements.preview', [
                'announcement' => $record,
                'typeColor' => self::typeColor($record->type),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * Copy the announcement as a new one scheduled for tomorrow, then open the copy to review it.
     */
    private static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::OutlinedSquare2Stack)
            ->authorize('replicate')
            ->requiresConfirmation()
            ->modalDescription('The copy starts tomorrow and has no end date, so it is not shown before you review it.')
            ->successNotificationTitle('Announcement duplicated')
            ->action(function (Action $action, Announcement $record): void {
                $announcement = resolve(AnnouncementService::class)->duplicate($record, self::administrator());

                $action->success();
                $action->redirect(AnnouncementResource::getUrl('edit', ['record' => $announcement]));
            });
    }

    /**
     * End a running announcement now, so users stop seeing it.
     */
    private static function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Unpublish now')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->visible(fn (Announcement $record): bool => $record->isRunning())
            ->authorize('update')
            ->requiresConfirmation()
            ->modalDescription('Users stop seeing this announcement right away.')
            ->successNotificationTitle('Announcement unpublished')
            ->failureNotificationTitle('The announcement is no longer running.')
            ->action(function (Action $action, Announcement $record): void {
                try {
                    resolve(AnnouncementService::class)->unpublish($record, self::administrator());
                } catch (LogicException) {
                    $action->failure();

                    return;
                }

                $action->success();
            });
    }

    private static function administrator(): User
    {
        $authenticatedUser = Filament::auth()->user();

        throw_unless($authenticatedUser instanceof User, LogicException::class, 'Only a signed-in administrator can change announcements.');

        return $authenticatedUser;
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        $typeOptions = [];

        foreach (AnnouncementType::cases() as $announcementType) {
            $typeOptions[$announcementType->value] = $announcementType->label();
        }

        return $typeOptions;
    }

    /**
     * @return array<string, string>
     */
    private static function audienceOptions(): array
    {
        $audienceOptions = [];

        foreach (AnnouncementAudience::cases() as $announcementAudience) {
            $audienceOptions[$announcementAudience->value] = $announcementAudience->label();
        }

        return $audienceOptions;
    }
}

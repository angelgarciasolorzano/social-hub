<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Tables;

use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Services\AnnouncementService;
use App\Support\Translation\TranslatedText;
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
                    ->label(TranslatedText::of('admin/announcements.fields.title'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('type')
                    ->label(TranslatedText::of('admin/announcements.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (AnnouncementType $state): string => $state->label())
                    ->color(fn (AnnouncementType $state): string => self::typeColor($state)),
                TextColumn::make('audience')
                    ->label(TranslatedText::of('admin/announcements.fields.audience'))
                    ->formatStateUsing(fn (AnnouncementAudience $state): string => $state->label()),
                TextColumn::make('display')
                    ->label(TranslatedText::of('admin/announcements.fields.display'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (AnnouncementDisplay $state): string => $state->label())
                    ->icon(fn (AnnouncementDisplay $state): Heroicon => self::displayIcon($state)),
                TextColumn::make('starts_at')
                    ->label(TranslatedText::of('admin/announcements.table.starts'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(TranslatedText::of('admin/announcements.table.ends'))
                    ->dateTime()
                    ->placeholder(TranslatedText::of('admin/announcements.table.no_end_date'))
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label(TranslatedText::of('admin/announcements.fields.created_by'))
                    ->placeholder(TranslatedText::of('admin/announcements.table.deleted_user')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(TranslatedText::of('admin/announcements.filters.type'))
                    ->options(self::typeOptions()),
                SelectFilter::make('audience')
                    ->label(TranslatedText::of('admin/announcements.filters.audience'))
                    ->options(self::audienceOptions()),
                SelectFilter::make('display')
                    ->label(TranslatedText::of('admin/announcements.filters.display'))
                    ->options(self::displayOptions()),
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
     * The icon of each presentation, shared by the table and the detail.
     */
    public static function displayIcon(AnnouncementDisplay $announcementDisplay): Heroicon
    {
        return match ($announcementDisplay) {
            AnnouncementDisplay::Toast => Heroicon::OutlinedChatBubbleBottomCenterText,
            AnnouncementDisplay::Dialog => Heroicon::OutlinedWindow,
        };
    }

    /**
     * Show the banner as users would see it, with its body sanitized.
     */
    private static function previewAction(): Action
    {
        return Action::make('preview')
            ->label(TranslatedText::of('admin/announcements.actions.preview'))
            ->icon(Heroicon::OutlinedEye)
            ->authorize('view')
            ->modalHeading(TranslatedText::of('admin/announcements.actions.preview_heading'))
            ->modalContent(fn (Announcement $record): View => view('admin::filament.announcements.preview', [
                'announcement' => $record,
                'typeColor' => self::typeColor($record->type),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(TranslatedText::of('admin/announcements.actions.close'));
    }

    /**
     * Copy the announcement as a new one scheduled for tomorrow, then open the copy to review it.
     */
    private static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label(TranslatedText::of('admin/announcements.actions.duplicate'))
            ->icon(Heroicon::OutlinedSquare2Stack)
            ->authorize('replicate')
            ->requiresConfirmation()
            ->modalDescription(TranslatedText::of('admin/announcements.actions.duplicate_description'))
            ->successNotificationTitle(TranslatedText::of('admin/announcements.actions.duplicated'))
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
            ->label(TranslatedText::of('admin/announcements.actions.unpublish'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->visible(fn (Announcement $record): bool => $record->isRunning())
            ->authorize('update')
            ->requiresConfirmation()
            ->modalDescription(TranslatedText::of('admin/announcements.actions.unpublish_description'))
            ->successNotificationTitle(TranslatedText::of('admin/announcements.actions.unpublished'))
            ->failureNotificationTitle(TranslatedText::of('admin/announcements.actions.no_longer_running'))
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

        throw_unless(
            $authenticatedUser instanceof User,
            LogicException::class,
            TranslatedText::of('admin/announcements.actions.administrator_only'),
        );

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
    private static function displayOptions(): array
    {
        $displayOptions = [];

        foreach (AnnouncementDisplay::cases() as $announcementDisplay) {
            $displayOptions[$announcementDisplay->value] = $announcementDisplay->label();
        }

        return $displayOptions;
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

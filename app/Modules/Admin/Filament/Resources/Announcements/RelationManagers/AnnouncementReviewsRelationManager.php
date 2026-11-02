<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\RelationManagers;

use App\Modules\Admin\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Models\Announcement;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Override;

class AnnouncementReviewsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'reviews';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return TranslatedText::of('admin/announcements.sections.history');
    }

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Announcement || $pageClass !== ViewAnnouncement::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->loadingSkeleton()
            ->columns([
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/common.fields.date'))
                    ->dateTime(),
                TextColumn::make('action')
                    ->label(TranslatedText::of('admin/announcements.review_history.action'))
                    ->formatStateUsing(fn (AnnouncementReviewAction $state): string => match ($state) {
                        AnnouncementReviewAction::Submitted => TranslatedText::of('admin/announcements.review_actions.submitted'),
                        AnnouncementReviewAction::Approved => TranslatedText::of('admin/announcements.review_actions.approved'),
                        AnnouncementReviewAction::Rejected => TranslatedText::of('admin/announcements.review_actions.rejected'),
                        AnnouncementReviewAction::Resubmitted => TranslatedText::of('admin/announcements.review_actions.resubmitted'),
                    }),
                TextColumn::make('comment')
                    ->label(TranslatedText::of('admin/announcements.review_history.comment'))
                    ->placeholder(TranslatedText::of('admin/common.none'))
                    ->wrap(),
                TextColumn::make('reviewedBy.name')
                    ->label(TranslatedText::of('admin/announcements.review_history.actor'))
                    ->placeholder(TranslatedText::of('admin/common.deleted_user')),
            ])
            ->query(function (): Builder {
                $model = $this->getOwnerRecord();

                throw_unless(
                    $model instanceof Announcement,
                    LogicException::class,
                    'The review history can only be listed for an announcement record.',
                );

                return $model->reviews()
                    ->getQuery()
                    ->with('reviewedBy:id,name');
            })
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

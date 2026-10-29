<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments\RelationManagers;

use App\Modules\Admin\Filament\Resources\Comments\Pages\ViewComment;
use App\Modules\Comment\Models\Comment;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class RepliesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'comments';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Comment || $pageClass !== ViewComment::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Comment::class);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->loadingSkeleton()
            ->recordTitleAttribute('content')
            ->columns([
                TextColumn::make('content')
                    ->label(TranslatedText::of('admin/comments.replies.table.reply'))
                    ->limit(70),
                TextColumn::make('user.name')
                    ->label(TranslatedText::of('admin/comments.fields.author')),
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/comments.fields.created_at'))
                    ->dateTime(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label(TranslatedText::of('admin/comments.replies.filters.creation_date'))
                    ->schema([
                        DatePicker::make('created_from')->label(TranslatedText::of('admin/common.filters.from')),
                        DatePicker::make('created_until')->label(TranslatedText::of('admin/common.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (\is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '>=', $date)
                                : $builder,
                        )
                        ->when(
                            $data['created_until'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (\is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '<=', $date)
                                : $builder,
                        )),
                SelectFilter::make('author')
                    ->label(TranslatedText::of('admin/comments.fields.author'))
                    ->relationship('user', 'name')
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordAction('viewReply')
            ->recordActions([
                Action::make('viewReply')
                    ->label(TranslatedText::of('admin/comments.replies.actions.view'))
                    ->icon(Heroicon::Eye)
                    ->color('gray')
                    ->authorize(fn (): bool => static::canViewForRecord(
                        $this->getOwnerRecord(),
                        ViewComment::class,
                    ))
                    ->modalHeading(TranslatedText::of('admin/comments.replies.actions.modal_heading'))
                    ->schema([
                        Section::make(TranslatedText::of('admin/comments.replies.modal.content_heading'))
                            ->description(TranslatedText::of('admin/comments.infolist.content.description'))
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                TextEntry::make('content')
                                    ->label(TranslatedText::of('admin/comments.fields.message'))
                                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                                    ->prose()
                                    ->columnSpanFull(),
                            ]),
                        Section::make(TranslatedText::of('admin/comments.replies.actions.modal_heading'))
                            ->description(TranslatedText::of('admin/comments.replies.modal.details_description'))
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label(TranslatedText::of('admin/comments.fields.author'))
                                    ->icon(Heroicon::OutlinedUser),
                                TextEntry::make('created_at')
                                    ->label(TranslatedText::of('admin/comments.fields.created_at'))
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->dateTime('Y-m-d H:i'),
                                TextEntry::make('edited_at')
                                    ->label(TranslatedText::of('admin/comments.fields.edited_at'))
                                    ->icon(Heroicon::OutlinedPencilSquare)
                                    ->dateTime('Y-m-d H:i')
                                    ->visible(static fn (Comment $record): bool => $record->edited_at !== null),
                                TextEntry::make('repliedToUser.name')
                                    ->label(TranslatedText::of('admin/comments.fields.replying_to'))
                                    ->icon(Heroicon::OutlinedUser)
                                    ->formatStateUsing(static fn (?string $state): ?string => filled($state) ? "@{$state}" : null)
                                    ->visible(static fn (Comment $record): bool => $record->repliedToUser instanceof User),
                            ])
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(TranslatedText::of('admin/comments.replies.actions.close')),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments\RelationManagers;

use App\Modules\Admin\Filament\Resources\Comments\Pages\ViewComment;
use App\Modules\Comment\Models\Comment;
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
            ->recordTitleAttribute('content')
            ->columns([
                TextColumn::make('content')
                    ->label('Reply')
                    ->limit(70),
                TextColumn::make('user.name')
                    ->label('Author'),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Creation date')
                    ->schema([
                        DatePicker::make('created_from')->label('From'),
                        DatePicker::make('created_until')->label('Until'),
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
                    ->label('Author')
                    ->relationship('user', 'name')
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordActions([
                Action::make('viewReply')
                    ->label('View reply')
                    ->icon(Heroicon::Eye)
                    ->color('gray')
                    ->authorize(fn (): bool => static::canViewForRecord(
                        $this->getOwnerRecord(),
                        ViewComment::class,
                    ))
                    ->modalHeading('Reply details')
                    ->schema([
                        Section::make('Reply content')
                            ->description('The complete message in this conversation.')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                TextEntry::make('content')
                                    ->label('Message')
                                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                                    ->prose()
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Reply details')
                            ->description('Author and timeline for this reply.')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Author')
                                    ->icon(Heroicon::OutlinedUser),
                                TextEntry::make('created_at')
                                    ->label('Created at')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->dateTime('Y-m-d H:i'),
                                TextEntry::make('edited_at')
                                    ->label('Edited at')
                                    ->icon(Heroicon::OutlinedPencilSquare)
                                    ->dateTime('Y-m-d H:i')
                                    ->visible(static fn (Comment $record): bool => $record->edited_at !== null),
                                TextEntry::make('repliedToUser.name')
                                    ->label('Replying to')
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
                    ->modalCancelActionLabel('Close'),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}

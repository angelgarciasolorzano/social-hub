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
                        TextEntry::make('content')
                            ->label('Reply')
                            ->columnSpanFull(),
                        TextEntry::make('user.name')
                            ->label('Author'),
                        TextEntry::make('created_at')
                            ->label('Created at')
                            ->dateTime('Y-m-d H:i'),
                        TextEntry::make('edited_at')
                            ->label('Edited at')
                            ->dateTime('Y-m-d H:i')
                            ->visible(static fn (Comment $record): bool => $record->edited_at !== null),
                        TextEntry::make('repliedToUser.name')
                            ->label('Replying to')
                            ->formatStateUsing(static fn (?string $state): ?string => filled($state) ? "@{$state}" : null)
                            ->visible(static fn (Comment $record): bool => $record->repliedToUser instanceof User),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}

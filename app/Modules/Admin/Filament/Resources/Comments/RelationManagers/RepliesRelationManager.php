<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments\RelationManagers;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Comments\Pages\ViewComment;
use App\Modules\Comment\Models\Comment;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            && $authenticatedUser->can(AdminPermission::AdminPostsView->value);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                TextColumn::make('content')
                    ->label('Reply'),
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
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

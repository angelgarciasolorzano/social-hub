<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\RelationManagers;

use App\Modules\Admin\Filament\Resources\Comments\CommentResource;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ViewPost;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class CommentsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'comments';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Post || $pageClass !== ViewPost::class) {
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user:id,name'))
            ->columns([
                TextColumn::make('content')
                    ->label(TranslatedText::of('admin/posts.comments.fields.content'))
                    ->limit(70),
                TextColumn::make('user.name')
                    ->label(TranslatedText::of('admin/posts.comments.fields.author')),
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/posts.comments.fields.created_at'))
                    ->dateTime(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label(TranslatedText::of('admin/posts.comments.filters.creation_date'))
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
                    ->label(TranslatedText::of('admin/posts.comments.filters.author'))
                    ->relationship('user', 'name')
                    ->searchable(),
                TernaryFilter::make('has_replies')
                    ->label(TranslatedText::of('admin/posts.comments.filters.reply_presence'))
                    ->placeholder(TranslatedText::of('admin/posts.comments.filters.all_comments'))
                    ->trueLabel(TranslatedText::of('admin/posts.comments.filters.has_replies'))
                    ->falseLabel(TranslatedText::of('admin/posts.comments.filters.no_replies'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('comments'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('comments'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye)
                    ->modal(false)
                    ->url(fn (Comment $comment): string => CommentResource::getUrl('view', ['record' => $comment])),
            ])
            ->toolbarActions([]);
    }
}

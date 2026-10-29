<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Post\Models\Post;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class PostsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'posts';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return TranslatedText::of('admin/users.relations.posts.title');
    }

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof User || $pageClass !== ViewUser::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord)
            && Gate::forUser($authenticatedUser)->allows('viewAny', Post::class);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->loadingSkeleton()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'user_id',
                    'content',
                    'created_at',
                ])
                ->with('media'))
            ->columns([
                ImageColumn::make('post_image')
                    ->label(TranslatedText::of('admin/users.relations.posts.image'))
                    ->state(function (Post $record): ?string {
                        $imageUrl = $record->getFirstMediaUrl(Post::POSTS_IMAGES_MEDIA_COLLECTION);

                        return filled($imageUrl) ? url($imageUrl) : null;
                    })
                    ->defaultImageUrl(asset('default-cover.svg'))
                    ->imageSize(64)
                    ->square()
                    ->alt(TranslatedText::of('admin/users.relations.posts.image_alt')),
                TextColumn::make('content')
                    ->label(TranslatedText::of('admin/users.relations.posts.post'))
                    ->limit(70)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/users.relations.posts.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('has_comments')
                    ->label(TranslatedText::of('admin/users.relations.posts.filters.comment_presence'))
                    ->placeholder(TranslatedText::of('admin/users.relations.posts.filters.all_posts'))
                    ->trueLabel(TranslatedText::of('admin/users.relations.posts.filters.has_comments'))
                    ->falseLabel(TranslatedText::of('admin/users.relations.posts.filters.no_comments'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('comments'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('comments'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('created_at')
                    ->label(TranslatedText::of('admin/users.relations.posts.filters.creation_date'))
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
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye)
                    ->url(fn (Post $post): string => PostResource::getUrl('view', ['record' => $post])),
            ])
            ->toolbarActions([]);
    }
}

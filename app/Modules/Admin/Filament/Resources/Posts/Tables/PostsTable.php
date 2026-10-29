<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Tables;

use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Post\Models\Post;
use App\Support\Translation\TranslatedText;
use DateTimeInterface;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user:id,name', 'media']))
            ->deferLoading()
            ->loadingSkeleton()
            ->columns([
                ImageColumn::make('post_image')
                    ->label(TranslatedText::of('admin/posts.table.image'))
                    ->state(function (Post $record): ?string {
                        $imageUrl = $record->getFirstMediaUrl(Post::POSTS_IMAGES_MEDIA_COLLECTION);

                        return filled($imageUrl) ? url($imageUrl) : null;
                    })
                    ->defaultImageUrl(asset('default-cover.svg'))
                    ->imageSize(64)
                    ->square()
                    ->alt(TranslatedText::of('admin/posts.table.image_alt')),
                TextColumn::make('content')
                    ->label(TranslatedText::of('admin/posts.table.post'))
                    ->limit(70)
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label(TranslatedText::of('admin/posts.table.author'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/posts.table.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('has_comments')
                    ->label(TranslatedText::of('admin/posts.filters.comment_presence'))
                    ->placeholder(TranslatedText::of('admin/posts.filters.all_posts'))
                    ->trueLabel(TranslatedText::of('admin/posts.filters.has_comments'))
                    ->falseLabel(TranslatedText::of('admin/posts.filters.no_comments'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('comments'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('comments'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('created_at')
                    ->label(TranslatedText::of('admin/posts.filters.creation_date'))
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
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye)
                    ->modal(false)
                    ->url(fn (Post $post): string => PostResource::getUrl('view', ['record' => $post])),
            ])
            ->toolbarActions([]);
    }
}

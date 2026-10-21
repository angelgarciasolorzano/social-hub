<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
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
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof User || $pageClass !== ViewUser::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord)
            && $authenticatedUser->can(AdminPermission::AdminPostsViewAny->value);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'user_id',
                    'content',
                    'created_at',
                ]))
            ->columns([
                ImageColumn::make('post_image')
                    ->label('Image')
                    ->state(function (Post $record): ?string {
                        $imageUrl = $record->getFirstMediaUrl(Post::POSTS_IMAGES_MEDIA_COLLECTION);

                        return filled($imageUrl) ? url($imageUrl) : null;
                    })
                    ->defaultImageUrl(asset('default-cover.svg'))
                    ->imageSize(64)
                    ->square()
                    ->alt('Post image'),
                TextColumn::make('content')
                    ->label('Post')
                    ->limit(70)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('has_comments')
                    ->label('Comment presence')
                    ->placeholder('All posts')
                    ->trueLabel('Has comments')
                    ->falseLabel('No comments')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('comments'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('comments'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('created_at')
                    ->label('Creation date')
                    ->schema([
                        DatePicker::make('created_from')->label('From'),
                        DatePicker::make('created_until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '>=', $date)
                                : $builder,
                        )
                        ->when(
                            $data['created_until'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (is_string($date) || $date instanceof DateTimeInterface)
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
                    ->url(fn (Post $post): string => PostResource::getUrl('view', ['record' => $post])),
            ])
            ->toolbarActions([]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Tables;

use App\Modules\Post\Models\Post;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user:id,name'))
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
                    ->limit(100)
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordActions([])
            ->toolbarActions([]);
    }
}

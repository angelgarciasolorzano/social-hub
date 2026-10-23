<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts;

use App\Modules\Admin\Filament\Resources\Posts\Pages\ListPosts;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ViewPost;
use App\Modules\Admin\Filament\Resources\Posts\RelationManagers\CommentsRelationManager;
use App\Modules\Admin\Filament\Resources\Posts\Schemas\PostInfolist;
use App\Modules\Admin\Filament\Resources\Posts\Tables\PostsTable;
use App\Modules\Admin\Filament\Resources\Posts\Widgets\PostsOverview;
use App\Modules\Post\Models\Post;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Override;

class PostResource extends Resource
{
    #[Override]
    protected static ?string $model = Post::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    public static function getWidgets(): array
    {
        return [
            PostsOverview::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return PostInfolist::configure($schema);
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    #[Override]
    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'view' => ViewPost::route('/{record}'),
        ];
    }
}

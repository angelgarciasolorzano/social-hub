<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ListPosts;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ViewPost;
use App\Modules\Admin\Filament\Resources\Posts\RelationManagers\CommentsRelationManager;
use App\Modules\Admin\Filament\Resources\Posts\Tables\PostsTable;
use App\Modules\Admin\Filament\Resources\Posts\Widgets\PostsOverview;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Override;

class PostResource extends Resource
{
    #[Override]
    protected static ?string $model = Post::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    public static function canViewAny(): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminPostsViewAny->value);
    }

    #[Override]
    public static function canView(Model $record): bool
    {
        if (! $record instanceof Post) {
            return false;
        }

        $owner = $record->user;
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && $owner instanceof User
            && $authenticatedUser->can(AdminPermission::AdminPostsView->value);
    }

    #[Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[Override]
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    #[Override]
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    #[Override]
    public static function canDeleteAny(): bool
    {
        return false;
    }

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
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('user.name')
                    ->label('Author'),
                TextEntry::make('created_at')
                    ->label('Created at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->label('Updated at')
                    ->dateTime(),
                TextEntry::make('content')
                    ->label('Content')
                    ->columnSpanFull(),
            ]);
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

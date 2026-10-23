<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments;

use App\Modules\Admin\Filament\Resources\Comments\Pages\ViewComment;
use App\Modules\Admin\Filament\Resources\Comments\RelationManagers\RepliesRelationManager;
use App\Modules\Admin\Filament\Resources\Comments\Schemas\CommentInfolist;
use App\Modules\Comment\Models\Comment;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Reading is authorized by CommentPolicy (viewAny/view). Its update/delete let authors change
 * their own comments in the application, so every write ability is pinned to false here to
 * keep the panel read-only, including for administrators who wrote the comment.
 */
class CommentResource extends Resource
{
    #[Override]
    protected static ?string $model = Comment::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static bool $shouldRegisterNavigation = false;

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

    #[Override]
    public static function canRestore(Model $record): bool
    {
        return false;
    }

    #[Override]
    public static function canRestoreAny(): bool
    {
        return false;
    }

    #[Override]
    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    #[Override]
    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommentInfolist::configure($schema);
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    #[Override]
    public static function getRelations(): array
    {
        return [
            RepliesRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    #[Override]
    public static function getPages(): array
    {
        return [
            'view' => ViewComment::route('/{record}'),
        ];
    }
}

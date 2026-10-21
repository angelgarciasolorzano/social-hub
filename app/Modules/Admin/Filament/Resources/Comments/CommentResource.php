<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Comments\Pages\ViewComment;
use App\Modules\Admin\Filament\Resources\Comments\RelationManagers\RepliesRelationManager;
use App\Modules\Admin\Filament\Resources\Comments\Schemas\CommentInfolist;
use App\Modules\Comment\Models\Comment;
use App\User\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Override;

class CommentResource extends Resource
{
    #[Override]
    protected static ?string $model = Comment::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static bool $shouldRegisterNavigation = false;

    #[Override]
    public static function canViewAny(): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminPostsView->value);
    }

    #[Override]
    public static function canView(Model $record): bool
    {
        if (! $record instanceof Comment) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
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

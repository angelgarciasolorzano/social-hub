<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users;

use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\ActivityLogsRelationManager;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\PostsRelationManager;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\TrustedDevicesRelationManager;
use App\Modules\Admin\Filament\Resources\Users\Schemas\UserInfolist;
use App\Modules\Admin\Filament\Resources\Users\Tables\UsersTable;
use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use App\User\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Override;

class UserResource extends Resource
{
    #[Override]
    protected static ?string $model = User::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    public static function getWidgets(): array
    {
        return [
            RegistrationSummaryWidget::class,
        ];
    }

    /**
     * @return array<class-string<RelationManager>|RelationGroup|RelationManagerConfiguration>
     */
    #[Override]
    public static function getRelations(): array
    {
        return [
            TrustedDevicesRelationManager::class,
            PostsRelationManager::class,
            ActivityLogsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}

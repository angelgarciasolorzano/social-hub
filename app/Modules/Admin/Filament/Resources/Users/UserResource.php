<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users;

use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\PostsRelationManager;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\TrustedDevicesRelationManager;
use App\Modules\Admin\Filament\Resources\Users\Tables\UsersTable;
use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use BackedEnum;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
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
        return $schema
            ->components([
                ImageEntry::make(UserImageType::PROFILE_PICTURE->value())
                    ->label('Profile picture')
                    ->state(fn (User $record): string => url($record->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value())))
                    ->imageHeight(96)
                    ->imageWidth(96)
                    ->circular()
                    ->alt('Profile picture'),
                ImageEntry::make(UserImageType::COVER_IMAGE->value())
                    ->label('Cover image')
                    ->state(fn (User $record): string => url($record->getFirstMediaUrl(UserImageType::COVER_IMAGE->value())))
                    ->imageHeight(200)
                    ->imageWidth('100%')
                    ->columnSpanFull()
                    ->alt('Cover image'),
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('email_verified_at')->dateTime(),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('last_login_at')->dateTime(),
            ]);
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

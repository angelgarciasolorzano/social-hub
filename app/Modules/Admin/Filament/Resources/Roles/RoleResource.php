<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles;

use App\Modules\Admin\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Admin\Filament\Resources\Roles\Pages\RolePermissionMatrix;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleActivityLogsRelationManager;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleUsersRelationManager;
use App\Modules\Admin\Filament\Resources\Roles\Schemas\RoleInfolist;
use App\Modules\Admin\Filament\Resources\Roles\Tables\RolesTable;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The Admin roles and their permissions: the system roles of the catalog and the custom roles created
 * from the panel. Access is decided by RolePolicy.
 */
class RoleResource extends Resource
{
    #[Override]
    protected static ?string $model = Role::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * The role's name in the current language, so titles and breadcrumbs never show the stored identifier.
     */
    #[Override]
    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Role ? $record->displayName() : '';
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return TranslatedText::of('admin/roles.resource.model_label');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return TranslatedText::of('admin/roles.resource.plural_model_label');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return TranslatedText::of('admin/roles.resource.navigation_label');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return TranslatedText::of('admin/roles.resource.navigation_group');
    }

    /**
     * Only the roles of the admin guard; a `web` role with a cataloged name is not an Admin role.
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        $builder = parent::getEloquentQuery();
        $builder->getQuery()->where('guard_name', Role::GUARD_NAME);

        return $builder;
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    /**
     * @return array<class-string<RelationManager>|RelationGroup|RelationManagerConfiguration>
     */
    #[Override]
    public static function getRelations(): array
    {
        return [
            RoleUsersRelationManager::class,
            RoleActivityLogsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'matrix' => RolePermissionMatrix::route('/matrix'),
            'view' => ViewRole::route('/{record}'),
        ];
    }
}

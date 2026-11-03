<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles;

use App\Modules\Admin\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\Schemas\RoleInfolist;
use App\Modules\Admin\Filament\Resources\Roles\Tables\RolesTable;
use App\Support\Translation\TranslatedText;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use Spatie\Permission\Models\Role;

/**
 * The Admin roles and their permissions. Access is decided by RolePolicy; the catalog is closed, so
 * roles are never created or deleted here.
 */
class RoleResource extends Resource
{
    private const string GUARD = 'admin';

    #[Override]
    protected static ?string $model = Role::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

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
        $builder->getQuery()->where('guard_name', self::GUARD);

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
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'view' => ViewRole::route('/{record}'),
        ];
    }
}

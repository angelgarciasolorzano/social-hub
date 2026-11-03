<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The Admin roles of a user with the permissions each grants, read-only: roles are changed with the
 * page's "Manage roles" action.
 */
class RolesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'roles';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return TranslatedText::of('admin/roles.users.relation.title');
    }

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof User || $pageClass !== ViewUser::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord)
            && Gate::forUser($authenticatedUser)->allows('viewAny', Role::class);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->loadingSkeleton()
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->getQuery()->where('roles.guard_name', 'admin');

                return $query->with('permissions');
            })
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(TranslatedText::of('admin/roles.users.relation.role'))
                    ->formatStateUsing(fn (string $state): string => AdminRole::tryFrom($state)?->label() ?? $state)
                    ->description(fn (Role $record): string => $record->name),
                TextColumn::make('description')
                    ->label(TranslatedText::of('admin/roles.users.relation.description'))
                    ->state(fn (Role $record): ?string => AdminRole::tryFrom($record->name)?->description())
                    ->placeholder(TranslatedText::of('admin/roles.table.not_editable'))
                    ->wrap(),
                TextColumn::make('permissions_list')
                    ->label(TranslatedText::of('admin/roles.users.relation.permissions'))
                    ->state(fn (Role $record): array => $record->permissions
                        ->map(static fn (Permission $permission): string => AdminPermission::tryFrom($permission->name)?->label() ?? $permission->name)
                        ->values()
                        ->all())
                    ->badge()
                    ->wrap(),
            ])
            ->paginated(false)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

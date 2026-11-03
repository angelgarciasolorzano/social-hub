<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Tables;

use App\Modules\Admin\Enums\AdminRole;
use App\Support\Translation\TranslatedText;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['permissions', 'users']))
            ->loadingSkeleton()
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(TranslatedText::of('admin/roles.table.name'))
                    ->formatStateUsing(fn (string $state): string => AdminRole::tryFrom($state)?->label() ?? $state)
                    ->description(fn (Role $record): string => $record->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchRoles($query, $search))
                    ->sortable(),
                TextColumn::make('description')
                    ->label(TranslatedText::of('admin/roles.table.description'))
                    ->state(fn (Role $record): ?string => AdminRole::tryFrom($record->name)?->description())
                    ->placeholder(TranslatedText::of('admin/roles.table.not_editable'))
                    ->tooltip(fn (Role $record): ?string => AdminRole::tryFrom($record->name) instanceof AdminRole
                        ? null
                        : TranslatedText::of('admin/roles.table.not_editable_tooltip'))
                    ->wrap(),
                TextColumn::make('permissions_count')
                    ->label(TranslatedText::of('admin/roles.table.permissions_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label(TranslatedText::of('admin/roles.table.users_count'))
                    ->numeric()
                    ->sortable(),
            ]);
    }

    /**
     * Match the stored name or the role's name in the current language, so "lector" finds the readers.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    private static function searchRoles(Builder $query, string $search): Builder
    {
        $matchingRoleNames = array_values(array_map(
            static fn (AdminRole $adminRole): string => $adminRole->value,
            array_filter(
                AdminRole::cases(),
                static fn (AdminRole $adminRole): bool => mb_stripos($adminRole->label(), $search) !== false,
            ),
        ));

        return $query->where(static fn (Builder $builder): Builder => $builder
            ->where('name', 'like', '%'.$search.'%')
            ->orWhereIn('name', $matchingRoleNames));
    }
}

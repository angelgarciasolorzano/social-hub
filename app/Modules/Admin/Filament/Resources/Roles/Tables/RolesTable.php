<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Tables;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RolesTable
{
    private const string TYPE_SYSTEM = 'system';

    private const string TYPE_CUSTOM = 'custom';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['permissions', 'users']))
            ->loadingSkeleton()
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(TranslatedText::of('admin/roles.table.name'))
                    ->formatStateUsing(fn (Role $record): string => $record->displayName())
                    ->description(fn (Role $record): string => $record->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchRoles($query, $search))
                    ->sortable(),
                TextColumn::make('is_system')
                    ->label(TranslatedText::of('admin/roles.types.column'))
                    ->badge()
                    ->formatStateUsing(fn (Role $record): string => self::typeLabel($record))
                    ->color(fn (Role $record): string => $record->isSystem() ? 'gray' : 'info')
                    ->icon(fn (Role $record): ?Heroicon => $record->isLocked() ? Heroicon::OutlinedLockClosed : null)
                    ->tooltip(fn (Role $record): string => self::typeTooltip($record)),
                TextColumn::make('description')
                    ->label(TranslatedText::of('admin/roles.table.description'))
                    ->state(fn (Role $record): ?string => $record->displayDescription())
                    ->wrap(),
                TextColumn::make('permissions_count')
                    ->label(TranslatedText::of('admin/roles.table.permissions_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label(TranslatedText::of('admin/roles.table.users_count'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(TranslatedText::of('admin/roles.types.filter'))
                    ->options([
                        self::TYPE_SYSTEM => TranslatedText::of('admin/roles.types.system'),
                        self::TYPE_CUSTOM => TranslatedText::of('admin/roles.types.custom'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::filterByType($query, $data['value'] ?? null)),
            ]);
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    private static function filterByType(Builder $query, mixed $type): Builder
    {
        return match ($type) {
            self::TYPE_SYSTEM => $query->where('is_system', true),
            self::TYPE_CUSTOM => $query->where('is_system', false),
            default => $query,
        };
    }

    private static function typeLabel(Role $role): string
    {
        return TranslatedText::of($role->isSystem() ? 'admin/roles.types.system' : 'admin/roles.types.custom');
    }

    private static function typeTooltip(Role $role): string
    {
        if ($role->isLocked()) {
            return TranslatedText::of('admin/roles.locked.tooltip');
        }

        return TranslatedText::of($role->isSystem() ? 'admin/roles.types.system_tooltip' : 'admin/roles.types.custom_tooltip');
    }

    /**
     * Match the stored name, the label of a custom role, or the name of a system role in the current
     * language, so "lector" finds the readers.
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
            ->orWhere('label', 'like', '%'.$search.'%')
            ->orWhere(static fn (Builder $systemRoles): Builder => $systemRoles
                ->where('is_system', true)
                ->whereIn('name', $matchingRoleNames)));
    }
}

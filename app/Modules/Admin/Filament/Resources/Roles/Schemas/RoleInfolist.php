<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Schemas;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Permission;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(TranslatedText::of('admin/roles.infolist.role.heading'))
                    ->description(TranslatedText::of('admin/roles.infolist.role.description'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('locked_notice')
                            ->hiddenLabel()
                            ->state(TranslatedText::of('admin/roles.locked.notice'))
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->color('warning')
                            ->visible(fn (Role $record): bool => $record->isLocked())
                            ->columnSpanFull(),
                        TextEntry::make('name')
                            ->label(TranslatedText::of('admin/roles.table.name'))
                            ->formatStateUsing(fn (Role $record): string => $record->displayName())
                            ->helperText(fn (Role $record): string => $record->name),
                        TextEntry::make('is_system')
                            ->label(TranslatedText::of('admin/roles.types.column'))
                            ->badge()
                            ->formatStateUsing(fn (Role $record): string => TranslatedText::of($record->isSystem() ? 'admin/roles.types.system' : 'admin/roles.types.custom'))
                            ->color(fn (Role $record): string => $record->isSystem() ? 'gray' : 'info')
                            ->icon(fn (Role $record): ?Heroicon => $record->isLocked() ? Heroicon::OutlinedLockClosed : null)
                            ->tooltip(fn (Role $record): string => TranslatedText::of(match (true) {
                                $record->isLocked() => 'admin/roles.locked.tooltip',
                                $record->isSystem() => 'admin/roles.types.system_tooltip',
                                default => 'admin/roles.types.custom_tooltip',
                            })),
                        TextEntry::make('description')
                            ->label(TranslatedText::of('admin/roles.table.description'))
                            ->state(fn (Role $record): ?string => $record->displayDescription())
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('guard_name')
                            ->label(TranslatedText::of('admin/roles.table.guard'))
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('users_count')
                            ->label(TranslatedText::of('admin/roles.infolist.users_count'))
                            ->state(fn (Role $record): int => $record->users()->count())
                            ->numeric(),
                    ]),
                Section::make(TranslatedText::of('admin/roles.infolist.permissions.heading'))
                    ->description(TranslatedText::of('admin/roles.infolist.permissions.description'))
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('no_permissions')
                            ->hiddenLabel()
                            ->state(TranslatedText::of('admin/roles.infolist.permissions.empty'))
                            ->color('gray')
                            ->visible(fn (Role $record): bool => self::permissionLabelsIn($record) === [])
                            ->columnSpanFull(),
                        ...array_map(self::areaEntry(...), self::areas()),
                    ]),
            ]);
    }

    /**
     * The panel areas of the catalog, in catalog order.
     *
     * @return list<string>
     */
    private static function areas(): array
    {
        return array_values(array_unique(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->area(),
            AdminPermission::cases(),
        )));
    }

    /**
     * The cataloged permissions the role grants in one area, as badges; hidden when there are none.
     */
    private static function areaEntry(string $area): TextEntry
    {
        return TextEntry::make('permissions_'.$area)
            ->label(TranslatedText::of('admin/roles.catalog.areas.'.$area))
            ->state(fn (Role $record): array => self::permissionLabelsIn($record, $area))
            ->badge()
            ->visible(fn (Role $record): bool => self::permissionLabelsIn($record, $area) !== []);
    }

    /**
     * The labels of the cataloged permissions the role grants, optionally only those of one area, in
     * catalog order. Reads the role's loaded permissions, so the whole section costs one query.
     *
     * @return list<string>
     */
    private static function permissionLabelsIn(Role $role, ?string $area = null): array
    {
        $grantedNames = $role->permissions
            ->map(static fn (Permission $permission): string => $permission->name)
            ->all();

        return array_values(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->label(),
            array_filter(
                AdminPermission::cases(),
                static fn (AdminPermission $adminPermission): bool => \in_array($adminPermission->value, $grantedNames, true)
                    && ($area === null || $adminPermission->area() === $area),
            ),
        ));
    }
}

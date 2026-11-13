<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Tables;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Concerns\DescribesActivityLogParties;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use WeakMap;

class RolesTable
{
    use DescribesActivityLogParties;

    /**
     * The alias of the date of each role's latest change, selected with the roles.
     */
    public const string LAST_CHANGED_AT_COLUMN = 'last_changed_at';

    /**
     * The areas shown in a row before the rest are folded away.
     */
    private const int AREAS_SHOWN = 3;

    /**
     * The latest changes and creations of the roles a table shows, loaded once per table and page of
     * records so the number of queries does not depend on how many roles there are.
     *
     * @var WeakMap<HasTable, array{recordKeys: string, latestChanges: array<int, ActivityLog>, creations: array<int, ActivityLog>}>|null
     */
    private static ?WeakMap $weakMap = null;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => self::withListingData($query))
            ->loadingSkeleton()
            ->defaultSort('name')
            ->groups([
                Group::make('is_system')
                    ->label(TranslatedText::of('admin/roles.table.groups.type'))
                    ->getTitleFromRecordUsing(fn (Role $record): string => self::typeLabel($record))
                    ->collapsible(),
            ])
            ->defaultGroup('is_system')
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
                TextColumn::make('areas')
                    ->label(TranslatedText::of('admin/roles.table.areas'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Role $record): array => self::areaLabelsOf($record))
                    ->limitList(self::AREAS_SHOWN)
                    ->expandableLimitedList()
                    ->toggleable(),
                TextColumn::make(self::LAST_CHANGED_AT_COLUMN)
                    ->label(TranslatedText::of('admin/roles.table.last_changed_at'))
                    ->dateTime()
                    ->placeholder(TranslatedText::of('admin/roles.table.not_recorded'))
                    ->description(fn (Role $record, HasTable $livewire): ?string => self::causerOf(self::changesOf($livewire)['latestChanges'][$record->id] ?? null))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(self::LAST_CHANGED_AT_COLUMN, $direction === 'desc' ? 'desc' : 'asc'))
                    ->toggleable(),
                TextColumn::make('created_by')
                    ->label(TranslatedText::of('admin/roles.table.created_by'))
                    ->state(fn (Role $record, HasTable $livewire): string => self::causerOf(self::changesOf($livewire)['creations'][$record->id] ?? null)
                        ?? TranslatedText::of('admin/roles.table.not_recorded'))
                    ->url(function (Role $record, HasTable $livewire): ?string {
                        $creation = self::changesOf($livewire)['creations'][$record->id] ?? null;

                        return $creation instanceof ActivityLog ? self::causerUrl($creation) : null;
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('includes_permission')
                    ->label(TranslatedText::of('admin/roles.table.filters.includes_permission'))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => self::permissionOptionsByArea())
                    ->query(fn (Builder $query, array $data): Builder => self::filterByPermissions($query, $data['values'] ?? [])),
                SelectFilter::make('covers_area')
                    ->label(TranslatedText::of('admin/roles.table.filters.covers_area'))
                    ->options(fn (): array => self::areaOptions())
                    ->query(fn (Builder $query, array $data): Builder => self::filterByArea($query, $data['value'] ?? null)),
                TernaryFilter::make('sensitive')
                    ->label(TranslatedText::of('admin/roles.table.filters.sensitive'))
                    ->placeholder(TranslatedText::of('admin/roles.table.filters.all'))
                    ->trueLabel(TranslatedText::of('admin/roles.table.filters.has_sensitive'))
                    ->falseLabel(TranslatedText::of('admin/roles.table.filters.no_sensitive'))
                    ->queries(
                        true: fn (Builder $query): Builder => self::filterBySensitivePermissions($query, hasSensitive: true),
                        false: fn (Builder $query): Builder => self::filterBySensitivePermissions($query, hasSensitive: false),
                    ),
                TernaryFilter::make('with_users')
                    ->label(TranslatedText::of('admin/roles.table.filters.with_users'))
                    ->placeholder(TranslatedText::of('admin/roles.table.filters.all'))
                    ->trueLabel(TranslatedText::of('admin/roles.table.filters.has_users'))
                    ->falseLabel(TranslatedText::of('admin/roles.table.filters.no_users'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('users'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('users'),
                    ),
                Filter::make('last_changed_between')
                    ->label(TranslatedText::of('admin/roles.table.filters.last_changed_between'))
                    ->schema([
                        DatePicker::make('last_changed_from')->label(TranslatedText::of('admin/roles.table.filters.last_changed_from')),
                        DatePicker::make('last_changed_until')->label(TranslatedText::of('admin/roles.table.filters.last_changed_until')),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => self::filterByLastChangeDate($query, $data)),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->deferFilters(false);
    }

    /**
     * Count the permissions and users of each role, load its permissions for its areas and select the
     * date of its latest change.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    private static function withListingData(Builder $query): Builder
    {
        $query->withCount(['permissions', 'users'])->with('permissions');
        $query->getQuery()->selectSub(Role::lastChangeDateSubquery()->toBase(), self::LAST_CHANGED_AT_COLUMN);

        return $query;
    }

    /**
     * The translated names of the areas the role's permissions belong to, in catalog order.
     *
     * @return list<string>
     */
    private static function areaLabelsOf(Role $role): array
    {
        $permissionNames = $role->permissions->pluck('name')->all();
        $areas = [];

        foreach (AdminPermission::cases() as $adminPermission) {
            if (\in_array($adminPermission->value, $permissionNames, true)) {
                $areas[$adminPermission->area()] = self::areaLabel($adminPermission->area());
            }
        }

        return array_values($areas);
    }

    /**
     * Who made a recorded change, or null when it was not recorded.
     */
    private static function causerOf(?ActivityLog $activityLog): ?string
    {
        return $activityLog instanceof ActivityLog ? self::causerLabel($activityLog) : null;
    }

    /**
     * The latest changes and creations of the roles the table shows now, keyed by role id, loaded once
     * for each page of records.
     *
     * @return array{recordKeys: string, latestChanges: array<int, ActivityLog>, creations: array<int, ActivityLog>}
     */
    private static function changesOf(HasTable $livewire): array
    {
        $tableRecords = $livewire->getTableRecords();
        $roleIds = [];

        foreach ($tableRecords instanceof Collection ? $tableRecords->all() : $tableRecords->items() as $tableRecord) {
            if ($tableRecord instanceof Role) {
                $roleIds[] = $tableRecord->id;
            }
        }

        $recordKeys = implode(',', $roleIds);
        self::$weakMap ??= new WeakMap;
        $changes = self::$weakMap[$livewire] ?? null;

        if ($changes !== null && $changes['recordKeys'] === $recordKeys) {
            return $changes;
        }

        return self::$weakMap[$livewire] = [
            'recordKeys' => $recordKeys,
            'latestChanges' => Role::latestChangesFor($roleIds),
            'creations' => Role::creationsFor($roleIds),
        ];
    }

    /**
     * The permissions of the catalog grouped by the translated name of their area, by stored name.
     *
     * @return array<string, array<string, string>>
     */
    private static function permissionOptionsByArea(): array
    {
        $permissionOptions = [];

        foreach (AdminPermission::cases() as $adminPermission) {
            $permissionOptions[self::areaLabel($adminPermission->area())][$adminPermission->value] = $adminPermission->label();
        }

        return $permissionOptions;
    }

    /**
     * The panel areas of the catalog, in catalog order, with their translated names.
     *
     * @return array<string, string>
     */
    private static function areaOptions(): array
    {
        $areaOptions = [];

        foreach (AdminPermission::cases() as $adminPermission) {
            $areaOptions[$adminPermission->area()] = self::areaLabel($adminPermission->area());
        }

        return $areaOptions;
    }

    private static function areaLabel(string $area): string
    {
        return TranslatedText::of('admin/roles.catalog.areas.'.$area);
    }

    /**
     * Keep the roles that hold every selected permission of the catalog.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    private static function filterByPermissions(Builder $query, mixed $selectedPermissions): Builder
    {
        $adminPermissions = array_filter(array_map(
            static fn (mixed $permissionName): ?AdminPermission => \is_string($permissionName) ? AdminPermission::tryFrom($permissionName) : null,
            \is_array($selectedPermissions) ? $selectedPermissions : [],
        ));

        foreach ($adminPermissions as $adminPermission) {
            $query->whereHas('permissions', static function (Builder $permissions) use ($adminPermission): Builder {
                $permissions->getQuery()->where('name', $adminPermission->value);

                return $permissions;
            });
        }

        return $query;
    }

    /**
     * Keep the roles that hold at least one permission of the selected area.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    private static function filterByArea(Builder $query, mixed $area): Builder
    {
        $areaPermissionNames = array_values(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->value,
            array_filter(AdminPermission::cases(), static fn (AdminPermission $adminPermission): bool => $adminPermission->area() === $area),
        ));

        if ($areaPermissionNames === []) {
            return $query;
        }

        return $query->whereHas('permissions', static function (Builder $permissions) use ($areaPermissionNames): Builder {
            $permissions->getQuery()->whereIn('name', $areaPermissionNames);

            return $permissions;
        });
    }

    /**
     * Keep the roles that hold at least one sensitive permission, or the roles that hold none.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    private static function filterBySensitivePermissions(Builder $query, bool $hasSensitive): Builder
    {
        $sensitivePermissionNames = array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->value,
            AdminPermission::sensitive(),
        );
        $constraint = static function (Builder $permissions) use ($sensitivePermissionNames): Builder {
            $permissions->getQuery()->whereIn('name', $sensitivePermissionNames);

            return $permissions;
        };

        return $hasSensitive
            ? $query->whereHas('permissions', $constraint)
            : $query->whereDoesntHave('permissions', $constraint);
    }

    /**
     * Keep the roles whose latest change falls between the chosen dates, both included; a role without
     * a recorded change never matches.
     *
     * @param  Builder<Role>  $query
     * @param  array<mixed>  $data
     * @return Builder<Role>
     */
    private static function filterByLastChangeDate(Builder $query, array $data): Builder
    {
        $changedFrom = self::filterDate($data['last_changed_from'] ?? null);
        $changedUntil = self::filterDate($data['last_changed_until'] ?? null);

        if ($changedFrom instanceof CarbonImmutable) {
            self::whereLastChangeDate($query, '>=', $changedFrom->startOfDay());
        }

        if ($changedUntil instanceof CarbonImmutable) {
            self::whereLastChangeDate($query, '<=', $changedUntil->endOfDay());
        }

        return $query;
    }

    /**
     * Compare each role's latest change date with a date.
     *
     * @param  Builder<Role>  $query
     * @param  '>='|'<='  $operator
     */
    private static function whereLastChangeDate(Builder $query, string $operator, CarbonImmutable $date): void
    {
        $query->getQuery()->where(static function (QueryBuilder $subquery): void {
            $builder = Role::lastChangeDateSubquery()->toBase();

            $subquery->from($builder->from)
                ->selectRaw('max(created_at)')
                ->mergeWheres($builder->wheres, $builder->getRawBindings()['where']);
        }, $operator, $date->toDateTimeString());
    }

    private static function filterDate(mixed $date): ?CarbonImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date);
        }

        return \is_string($date) && $date !== '' ? CarbonImmutable::parse($date) : null;
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

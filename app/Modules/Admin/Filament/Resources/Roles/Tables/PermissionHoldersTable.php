<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Tables;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Modules\Admin\Filament\Concerns\DescribesPanelMfa;
use App\Modules\Admin\Filament\Resources\Roles\Pages\PermissionHolders;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Closure;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The users holding one permission through roles of the `admin` guard: their status, panel MFA, last
 * sign-in and the roles that grant it.
 */
class PermissionHoldersTable
{
    use DescribesAccountStatus;
    use DescribesPanelMfa;

    /**
     * `$selectedPermission` is read when the rows load, so picking another permission shows its holders;
     * without one the table has no rows.
     *
     * @param  Closure(): ?AdminPermission  $selectedPermission
     */
    public static function configure(Table $table, Closure $selectedPermission): Table
    {
        return $table
            ->query(fn (): Builder => self::holdersQuery($selectedPermission()))
            ->heading(TranslatedText::of('admin/roles.access.users.heading'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(TranslatedText::of('admin/roles.relations.users.name'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (User $record): ?string => UserResource::canView($record)
                        ? UserResource::getUrl('view', ['record' => $record])
                        : null),
                TextColumn::make('email')
                    ->label(TranslatedText::of('admin/roles.relations.users.email'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(TranslatedText::of('admin/common.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                    ->color(fn (AccountStatus $state): string => self::accountStatusColor($state)),
                self::panelMfaColumn(),
                TextColumn::make('last_login_at')
                    ->label(TranslatedText::of('admin/roles.relations.users.columns.last_login_at'))
                    ->dateTime()
                    ->placeholder(TranslatedText::of('admin/common.never'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('via_roles')
                    ->label(TranslatedText::of('admin/roles.access.users.via_roles'))
                    ->badge()
                    ->state(fn (User $record): array => array_map(
                        static fn (Role $role): string => $role->displayName(),
                        Role::inDisplayOrder($record->roles),
                    )),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(TranslatedText::of('admin/roles.relations.users.filters.status'))
                    ->options(self::accountStatusOptions()),
                self::panelMfaFilter(),
            ])
            ->emptyStateHeading(TranslatedText::of('admin/roles.access.users.empty'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * The holders of the permission with only the roles that grant it loaded; no rows without one.
     *
     * @return Builder<User>
     */
    private static function holdersQuery(?AdminPermission $adminPermission): Builder
    {
        if (! $adminPermission instanceof AdminPermission) {
            return User::query()->whereKey([]);
        }

        return PermissionHolders::holdersQuery($adminPermission)->with([
            'roles' => static function (Relation $roles) use ($adminPermission): void {
                Role::grantingPermission($roles->getQuery(), $adminPermission);
            },
        ]);
    }
}

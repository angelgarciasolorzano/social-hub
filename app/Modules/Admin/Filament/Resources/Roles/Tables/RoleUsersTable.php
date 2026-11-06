<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Tables;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Modules\Admin\Filament\Concerns\DescribesActivityLogParties;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The users holding one role: since when and from whom they hold it, whether they can enter the panel
 * (its MFA), when they last signed in and which other roles they hold.
 */
class RoleUsersTable
{
    use DescribesAccountStatus;
    use DescribesActivityLogParties;

    /**
     * The alias of the date of each user's latest grant of the role, selected with the users.
     */
    public const string ASSIGNED_AT_COLUMN = 'role_assigned_at';

    /**
     * Configure the table; `$grantsByUser` returns the latest grants of the users on the page, loaded
     * once, keyed by user id.
     *
     * @param  Closure(): array<int, ActivityLog>  $grantsByUser
     */
    public static function configure(Table $table, Role $role, Closure $grantsByUser): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->addSelect([self::ASSIGNED_AT_COLUMN => $role->latestGrantDateSubquery()])
                ->with('roles'))
            ->loadingSkeleton()
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
                TextColumn::make(self::ASSIGNED_AT_COLUMN)
                    ->label(TranslatedText::of('admin/roles.relations.users.columns.assigned_at'))
                    ->dateTime()
                    ->placeholder(TranslatedText::of('admin/roles.relations.users.not_recorded'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(self::ASSIGNED_AT_COLUMN, $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('assigned_by')
                    ->label(TranslatedText::of('admin/roles.relations.users.columns.assigned_by'))
                    ->state(function (User $record) use ($grantsByUser): string {
                        $grant = $grantsByUser()[$record->id] ?? null;

                        return $grant instanceof ActivityLog
                            ? self::causerLabel($grant)
                            : TranslatedText::of('admin/roles.relations.users.not_recorded');
                    })
                    ->url(function (User $record) use ($grantsByUser): ?string {
                        $grant = $grantsByUser()[$record->id] ?? null;

                        return $grant instanceof ActivityLog ? self::causerUrl($grant) : null;
                    }),
                IconColumn::make('panel_mfa')
                    ->label(TranslatedText::of('admin/roles.relations.users.columns.panel_mfa'))
                    ->state(fn (User $record): bool => $record->getRawOriginal('app_authentication_secret') !== null)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedShieldCheck)
                    ->falseIcon(Heroicon::OutlinedShieldExclamation)
                    ->tooltip(fn (bool $state): string => TranslatedText::of($state
                        ? 'admin/roles.relations.users.mfa.configured'
                        : 'admin/roles.relations.users.mfa.missing'))
                    ->toggleable(),
                TextColumn::make('last_login_at')
                    ->label(TranslatedText::of('admin/roles.relations.users.columns.last_login_at'))
                    ->dateTime()
                    ->placeholder(TranslatedText::of('admin/common.never'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('other_roles')
                    ->label(TranslatedText::of('admin/roles.relations.users.columns.other_roles'))
                    ->badge()
                    ->state(fn (User $record): array => self::otherRoleNames($record, $role))
                    ->toggleable(),
            ])
            ->emptyStateHeading(TranslatedText::of('admin/roles.relations.users.empty'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * The shown names of the other roles of the `admin` guard the user holds, in display order.
     *
     * @return list<string>
     */
    private static function otherRoleNames(User $user, Role $role): array
    {
        $otherRoles = [];

        foreach ($user->roles as $userRole) {
            if ($userRole->guard_name === Role::GUARD_NAME && ! $userRole->is($role)) {
                $otherRoles[] = $userRole;
            }
        }

        return array_map(static fn (Role $otherRole): string => $otherRole->displayName(), Role::inDisplayOrder($otherRoles));
    }
}

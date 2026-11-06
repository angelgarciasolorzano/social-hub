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
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
     * The choices of the "no sign-in in" filter, in days.
     *
     * @var list<int>
     */
    private const array INACTIVITY_DAYS = [30, 90, 180];

    /**
     * The days without a sign-in after which the summary counts a holder as inactive.
     */
    private const int SUMMARY_INACTIVITY_DAYS = 90;

    /**
     * How many other roles a row shows before the rest fold under "and N more".
     */
    private const int OTHER_ROLES_SHOWN = 2;

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
            ->description(fn (): ?string => self::holdersSummary($role))
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
                    ->listWithLineBreaks()
                    ->limitList(self::OTHER_ROLES_SHOWN)
                    ->expandableLimitedList()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(TranslatedText::of('admin/roles.relations.users.filters.status'))
                    ->options(self::accountStatusOptions()),
                TernaryFilter::make('panel_mfa')
                    ->label(TranslatedText::of('admin/roles.relations.users.filters.panel_mfa'))
                    ->placeholder(TranslatedText::of('admin/roles.relations.users.filters.all_holders'))
                    ->trueLabel(TranslatedText::of('admin/roles.relations.users.mfa.configured'))
                    ->falseLabel(TranslatedText::of('admin/roles.relations.users.mfa.missing'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('app_authentication_secret'),
                        false: fn (Builder $query): Builder => $query->whereNull('app_authentication_secret'),
                    ),
                SelectFilter::make('also_has_role')
                    ->label(TranslatedText::of('admin/roles.relations.users.filters.also_has_role'))
                    ->multiple()
                    ->options(fn (): array => self::otherRoleOptions($role))
                    ->query(fn (Builder $query, array $data): Builder => self::filterByOtherRoles($query, $data['values'] ?? [], $role)),
                Filter::make('assigned_between')
                    ->label(TranslatedText::of('admin/roles.relations.users.filters.assigned_between'))
                    ->schema([
                        DatePicker::make('assigned_from')->label(TranslatedText::of('admin/roles.relations.users.filters.assigned_from')),
                        DatePicker::make('assigned_until')->label(TranslatedText::of('admin/roles.relations.users.filters.assigned_until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::filterByAssignmentDate($query, $data, $role)),
                SelectFilter::make('inactive_for')
                    ->label(TranslatedText::of('admin/roles.relations.users.filters.inactive_for'))
                    ->options(self::inactivityOptions())
                    ->query(fn (Builder $query, array $data): Builder => self::filterByInactivity($query, $data['value'] ?? null)),
            ])
            ->emptyStateHeading(TranslatedText::of('admin/roles.relations.users.empty'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * How many holders of the role lack the panel MFA, did not sign in for 90 days or are suspended, in
     * one query, or nothing when nobody holds the role.
     */
    public static function holdersSummary(Role $role): ?string
    {
        $summary = User::query()
            ->whereHas('roles', static function (Builder $roles) use ($role): Builder {
                $roles->getQuery()->where('roles.id', $role->getKey());

                return $roles;
            })
            ->selectRaw(
                'count(*) as holders, '
                .'coalesce(sum(case when app_authentication_secret is null then 1 else 0 end), 0) as without_mfa, '
                .'coalesce(sum(case when last_login_at is null or last_login_at < ? then 1 else 0 end), 0) as inactive, '
                .'coalesce(sum(case when status = ? then 1 else 0 end), 0) as suspended',
                [now()->subDays(self::SUMMARY_INACTIVITY_DAYS), AccountStatus::Suspended->value],
            )
            ->toBase()
            ->first();

        $summaryRow = \is_object($summary) ? get_object_vars($summary) : [];

        if (self::summaryCount($summaryRow, 'holders') === 0) {
            return null;
        }

        return TranslatedText::of('admin/roles.relations.users.summary', [
            'without_mfa' => self::summaryCount($summaryRow, 'without_mfa'),
            'inactive' => self::summaryCount($summaryRow, 'inactive'),
            'suspended' => self::summaryCount($summaryRow, 'suspended'),
        ]);
    }

    /**
     * One count of the summary row, as a number.
     *
     * @param  array<mixed>  $summaryRow
     */
    private static function summaryCount(array $summaryRow, string $column): int
    {
        $count = $summaryRow[$column] ?? null;

        return is_numeric($count) ? (int) $count : 0;
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

    /**
     * The other roles of the `admin` guard, in display order, by stored name.
     *
     * @return array<string, string>
     */
    private static function otherRoleOptions(Role $role): array
    {
        $otherRoleOptions = [];
        $otherRoles = Role::query()->where('guard_name', Role::GUARD_NAME)->whereKeyNot($role->getKey())->get();

        foreach (Role::inDisplayOrder($otherRoles) as $otherRole) {
            $otherRoleOptions[$otherRole->name] = $otherRole->displayName();
        }

        return $otherRoleOptions;
    }

    /**
     * Keep the users who also hold any of the selected other roles of the `admin` guard.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function filterByOtherRoles(Builder $query, mixed $selectedRoleNames, Role $role): Builder
    {
        $roleNames = array_values(array_filter(
            \is_array($selectedRoleNames) ? $selectedRoleNames : [],
            static fn (mixed $roleName): bool => \is_string($roleName) && $roleName !== $role->name,
        ));

        if ($roleNames === []) {
            return $query;
        }

        return $query->whereHas('roles', static function (Builder $roles) use ($roleNames): Builder {
            $roles->getQuery()->where('guard_name', Role::GUARD_NAME)->whereIn('name', $roleNames);

            return $roles;
        });
    }

    /**
     * Keep the users whose latest grant of the role falls between the chosen dates, both included.
     *
     * @param  Builder<User>  $query
     * @param  array<mixed>  $data
     * @return Builder<User>
     */
    private static function filterByAssignmentDate(Builder $query, array $data, Role $role): Builder
    {
        $assignedFrom = self::filterDate($data['assigned_from'] ?? null);
        $assignedUntil = self::filterDate($data['assigned_until'] ?? null);

        if ($assignedFrom instanceof CarbonImmutable) {
            self::whereLatestGrantDate($query, $role, '>=', $assignedFrom->startOfDay());
        }

        if ($assignedUntil instanceof CarbonImmutable) {
            self::whereLatestGrantDate($query, $role, '<=', $assignedUntil->endOfDay());
        }

        return $query;
    }

    /**
     * Compare each user's latest grant date of the role with a date; users without a grant never match.
     *
     * @param  Builder<User>  $query
     * @param  '>='|'<='  $operator
     */
    private static function whereLatestGrantDate(Builder $query, Role $role, string $operator, CarbonImmutable $date): void
    {
        $query->getQuery()->where(static function (QueryBuilder $subquery) use ($role): void {
            $builder = $role->latestGrantDateSubquery()->toBase();

            $subquery->from($builder->from)
                ->selectRaw('max(created_at)')
                ->mergeWheres($builder->wheres, $builder->getRawBindings()['where']);
        }, $operator, $date->toDateTimeString());
    }

    /**
     * The days of each "no sign-in in" option, with their text.
     *
     * @return array<int, string>
     */
    private static function inactivityOptions(): array
    {
        $inactivityOptions = [];

        foreach (self::INACTIVITY_DAYS as $days) {
            $inactivityOptions[$days] = TranslatedText::of('admin/roles.relations.users.filters.inactive_option', ['days' => $days]);
        }

        return $inactivityOptions;
    }

    /**
     * Keep the users who never signed in or did not sign in during the chosen number of days.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function filterByInactivity(Builder $query, mixed $selectedDays): Builder
    {
        $days = is_numeric($selectedDays) ? (int) $selectedDays : null;

        if (! \in_array($days, self::INACTIVITY_DAYS, true)) {
            return $query;
        }

        $query->getQuery()->where(static fn (QueryBuilder $builder): QueryBuilder => $builder
            ->whereNull('last_login_at')
            ->orWhere('last_login_at', '<', now()->subDays($days)));

        return $query;
    }

    /**
     * A date chosen in a filter, or null when none was chosen.
     */
    private static function filterDate(mixed $date): ?CarbonImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date);
        }

        return \is_string($date) && $date !== '' ? CarbonImmutable::parse($date) : null;
    }
}

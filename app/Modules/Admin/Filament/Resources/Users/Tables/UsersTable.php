<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Tables;

use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class UsersTable
{
    use DescribesAccountStatus;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['media', 'roles']))
            ->deferLoading()
            ->loadingSkeleton()
            ->columns([
                ImageColumn::make('profile_picture')
                    ->label(TranslatedText::of('admin/users.fields.profile_picture'))
                    ->state(fn (User $record): string => url(
                        $record->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
                    ))
                    ->defaultImageUrl(asset(User::DEFAULT_PROFILE_PICTURE_PATH))
                    ->imageSize(40)
                    ->circular()
                    ->alt(fn (User $record): string => TranslatedText::of('admin/users.table.profile_picture_alt', ['name' => $record->name])),
                TextColumn::make('name')
                    ->label(TranslatedText::of('admin/users.table.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(TranslatedText::of('admin/users.table.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(TranslatedText::of('admin/common.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                    ->color(fn (AccountStatus $state): string => self::accountStatusColor($state))
                    ->sortable(),
                TextColumn::make('admin_roles')
                    ->label(TranslatedText::of('admin/roles.users.column'))
                    ->state(fn (User $record): array => self::adminRoleLabels($record))
                    ->badge()
                    ->color('gray')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder(TranslatedText::of('admin/roles.users.no_roles'))
                    ->visible(self::canViewRoles(...))
                    ->toggleable(),
                TextColumn::make('email_verified_at')
                    ->label(TranslatedText::of('admin/users.table.email_verified_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/users.table.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label(TranslatedText::of('admin/users.table.last_login_at'))
                    ->dateTime()
                    ->placeholder(TranslatedText::of('admin/common.never'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(TranslatedText::of('admin/users.account_status.label'))
                    ->options(self::accountStatusOptions()),
                SelectFilter::make('admin_roles')
                    ->label(TranslatedText::of('admin/roles.users.filter'))
                    ->options(self::adminRoleOptions())
                    ->multiple()
                    ->visible(self::canViewRoles(...))
                    ->query(fn (Builder $query, array $data): Builder => self::filterByAdminRoles($query, $data['values'] ?? [])),
                TernaryFilter::make('email_verified_at')
                    ->label(TranslatedText::of('admin/users.fields.email_verification'))
                    ->nullable(),
                Filter::make('registration_date')
                    ->label(TranslatedText::of('admin/users.table.filters.registration_date'))
                    ->schema([
                        DatePicker::make('from')->label(TranslatedText::of('admin/common.filters.from')),
                        DatePicker::make('until')->label(TranslatedText::of('admin/common.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (\is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '>=', $date)
                                : $builder,
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (\is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '<=', $date)
                                : $builder,
                        )),
                TernaryFilter::make('has_posts')
                    ->label(TranslatedText::of('admin/users.table.filters.post_presence'))
                    ->placeholder(TranslatedText::of('admin/users.table.filters.all_users'))
                    ->trueLabel(TranslatedText::of('admin/users.table.filters.has_posts'))
                    ->falseLabel(TranslatedText::of('admin/users.table.filters.no_posts'))
                    ->queries(
                        true: fn (Builder $builder): Builder => $builder->whereHas('posts'),
                        false: fn (Builder $builder): Builder => $builder->whereDoesntHave('posts'),
                        blank: fn (Builder $builder): Builder => $builder,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye)
                    ->modal(false)
                    ->url(fn (User $user): string => UserResource::getUrl('view', ['record' => $user])),
            ]);
    }

    private static function canViewRoles(): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Role::class);
    }

    /**
     * The labels of the user's admin roles, from the roles loaded with the page.
     *
     * @return array<int, string>
     */
    private static function adminRoleLabels(User $user): array
    {
        return $user->roles
            ->filter(static fn (Role $role): bool => $role->guard_name === Role::GUARD_NAME)
            ->map(static fn (Role $role): string => $role->displayName())
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function adminRoleOptions(): array
    {
        $adminRoleOptions = [];

        foreach (Role::inDisplayOrder(Role::query()->where('guard_name', Role::GUARD_NAME)->get()) as $role) {
            $adminRoleOptions[$role->name] = $role->displayName();
        }

        return $adminRoleOptions;
    }

    /**
     * Keep the users holding any of the selected roles of the admin guard, system or custom.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function filterByAdminRoles(Builder $query, mixed $selectedRoleNames): Builder
    {
        $roleNames = array_values(array_filter(
            \is_array($selectedRoleNames) ? $selectedRoleNames : [],
            \is_string(...),
        ));

        if ($roleNames === []) {
            return $query;
        }

        return $query->whereHas('roles', static function (Builder $roles) use ($roleNames): Builder {
            $roles->getQuery()->where('guard_name', Role::GUARD_NAME)->whereIn('name', $roleNames);

            return $roles;
        });
    }
}

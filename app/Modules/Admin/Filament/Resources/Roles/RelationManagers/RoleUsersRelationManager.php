<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\RelationManagers;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Filament\Concerns\ConfirmsCurrentPassword;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\Tables\RoleUsersTable;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Override;

/**
 * The users holding a role, with the columns of `RoleUsersTable`. An administrator who may manage the
 * roles of other users can add users to the role and remove it from them; their own account is never
 * offered, as on the user's detail.
 */
class RoleUsersRelationManager extends RelationManager
{
    use ConfirmsCurrentPassword;

    private const string USERS_FIELD = 'users';

    private const string ADD_PASSWORD_THROTTLE_PREFIX = 'admin-roles:add-users:';

    private const string REMOVE_PASSWORD_THROTTLE_PREFIX = 'admin-roles:remove-user:';

    private const int USER_SEARCH_LIMIT = 50;

    /**
     * The latest grants of the role to the users on the current page, loaded once per request.
     *
     * @var array<int, ActivityLog>|null
     */
    private ?array $latestGrantsByUser = null;

    #[Override]
    protected static string $relationship = 'users';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return TranslatedText::of('admin/roles.relations.users.title');
    }

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Role || $pageClass !== ViewRole::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord)
            && Gate::forUser($authenticatedUser)->allows('viewAny', User::class);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return RoleUsersTable::configure($table, $this->role(), fn (): array => $this->latestGrantsByUser())
            ->headerActions([$this->addUsersAction()])
            ->recordActions([$this->removeFromRoleAction()])
            ->toolbarActions([]);
    }

    /**
     * The latest grant of the role to each user on the current page, loaded once per request.
     *
     * @return array<int, ActivityLog>
     */
    private function latestGrantsByUser(): array
    {
        if ($this->latestGrantsByUser !== null) {
            return $this->latestGrantsByUser;
        }

        $tableRecords = $this->getTableRecords();
        $userIds = [];

        foreach ($tableRecords instanceof Collection ? $tableRecords->all() : $tableRecords->items() as $tableRecord) {
            if ($tableRecord instanceof User) {
                $userIds[] = $tableRecord->id;
            }
        }

        return $this->latestGrantsByUser = $this->role()->latestGrantsFor($userIds);
    }

    private function addUsersAction(): Action
    {
        return Action::make('addUsers')
            ->label(TranslatedText::of('admin/roles.relations.users.add.label'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => self::administrator()?->can(AdminPermission::AdminUsersRoles->value) ?? false)
            ->modalHeading(fn (): string => TranslatedText::of('admin/roles.relations.users.add.modal_heading', [
                'role' => $this->role()->displayName(),
            ]))
            ->modalSubmitActionLabel(TranslatedText::of('admin/roles.relations.users.add.submit'))
            ->schema([
                Select::make(self::USERS_FIELD)
                    ->label(TranslatedText::of('admin/roles.relations.users.add.field'))
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->addableUserOptions($search))
                    ->getOptionLabelsUsing(fn (array $values): array => $this->addableUserLabels($values)),
                self::currentPasswordField(),
            ])
            ->action(fn (array $data, AdminAuthorizationService $adminAuthorizationService) => $this->addUsers($data, $adminAuthorizationService));
    }

    private function removeFromRoleAction(): Action
    {
        return Action::make('removeFromRole')
            ->label(TranslatedText::of('admin/roles.relations.users.remove.label'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->authorize(fn (User $record): bool => self::mayManageRolesOf($record))
            ->modalHeading(fn (User $record): string => TranslatedText::of('admin/roles.relations.users.remove.modal_heading', [
                'role' => $this->role()->displayName(),
                'name' => $record->name,
            ]))
            ->modalDescription(TranslatedText::of('admin/roles.relations.users.remove.modal_description'))
            ->modalIcon(Heroicon::OutlinedUserMinus)
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(TranslatedText::of('admin/roles.relations.users.remove.submit'))
            ->schema([self::currentPasswordField()])
            ->action(fn (User $record, array $data, AdminAuthorizationService $adminAuthorizationService) => $this->removeFromRole($record, $data, $adminAuthorizationService));
    }

    /**
     * Confirm the password and give the role to every selected user the administrator may manage, in one
     * transaction, with one activity per user who did not have it yet.
     *
     * @param  array<mixed>  $data
     *
     * @throws ValidationException when the password is wrong or its attempts are exhausted
     */
    private function addUsers(array $data, AdminAuthorizationService $adminAuthorizationService): void
    {
        $administrator = self::administrator();
        $currentPassword = $data[self::CURRENT_PASSWORD_FIELD] ?? null;
        $selectedUserIds = $data[self::USERS_FIELD] ?? [];

        abort_unless($administrator instanceof User, 403);
        abort_unless(\is_string($currentPassword) && \is_array($selectedUserIds), 422);

        self::ensureCurrentPassword(
            $administrator,
            $currentPassword,
            self::ADD_PASSWORD_THROTTLE_PREFIX,
            'mountedActions.0.data.'.self::CURRENT_PASSWORD_FIELD,
        );

        $users = User::query()->whereKey(array_filter($selectedUserIds, is_numeric(...)))->get()
            ->filter(static fn (User $user): bool => self::mayManageRolesOf($user));

        try {
            $addedCount = DB::transaction(function () use ($users, $administrator, $adminAuthorizationService): int {
                $addedCount = 0;

                foreach ($users as $user) {
                    if ($adminAuthorizationService->grantRole($user, $this->role(), $administrator)) {
                        $addedCount++;
                    }
                }

                return $addedCount;
            });
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            $this->notifyFailure($adminAuthorizationException);

            return;
        }

        Notification::make()
            ->title(trans_choice('admin/roles.relations.users.add.success', $addedCount, ['count' => $addedCount]))
            ->success()
            ->send();

        $this->dispatch(ViewRole::ROLE_USERS_CHANGED_EVENT);
    }

    /**
     * Confirm the password and take the role away from the user.
     *
     * @param  array<mixed>  $data
     *
     * @throws ValidationException when the password is wrong or its attempts are exhausted
     */
    private function removeFromRole(User $user, array $data, AdminAuthorizationService $adminAuthorizationService): void
    {
        $administrator = self::administrator();
        $currentPassword = $data[self::CURRENT_PASSWORD_FIELD] ?? null;

        abort_unless($administrator instanceof User && self::mayManageRolesOf($user), 403);
        abort_unless(\is_string($currentPassword), 422);

        self::ensureCurrentPassword(
            $administrator,
            $currentPassword,
            self::REMOVE_PASSWORD_THROTTLE_PREFIX,
            'mountedActions.0.data.'.self::CURRENT_PASSWORD_FIELD,
        );

        try {
            $adminAuthorizationService->revokeRole($user, $this->role(), $administrator);
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            $this->notifyFailure($adminAuthorizationException);

            return;
        }

        Notification::make()
            ->title(TranslatedText::of('admin/roles.relations.users.remove.success', [
                'name' => $user->name,
                'role' => $this->role()->displayName(),
            ]))
            ->success()
            ->send();

        $this->dispatch(ViewRole::ROLE_USERS_CHANGED_EVENT);
    }

    /**
     * Users who do not hold the role yet, other than the administrator, matching the search by name or
     * email.
     *
     * @return array<int|string, string>
     */
    private function addableUserOptions(string $search): array
    {
        $options = [];

        $users = $this->addableUsers()
            ->where(static fn (Builder $builder): Builder => $builder
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit(self::USER_SEARCH_LIMIT)
            ->get();

        foreach ($users as $user) {
            $options[$user->id] = $this->userLabel($user);
        }

        return $options;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<int|string, string>
     */
    private function addableUserLabels(array $values): array
    {
        $labels = [];

        foreach ($this->addableUsers()->whereKey(array_filter($values, is_numeric(...)))->get() as $user) {
            $labels[$user->id] = $this->userLabel($user);
        }

        return $labels;
    }

    /**
     * @return Builder<User>
     */
    private function addableUsers(): Builder
    {
        $role = $this->role();
        $builder = User::query()->whereDoesntHave('roles', static fn (Builder $roles): Builder => $roles->whereKey($role->getKey()));

        $administrator = self::administrator();

        if ($administrator instanceof User) {
            $builder->whereKeyNot($administrator->getKey());
        }

        return $builder;
    }

    private function role(): Role
    {
        $model = $this->getOwnerRecord();

        if ($model instanceof Role) {
            return $model;
        }

        abort(404);
    }

    private static function administrator(): ?User
    {
        $administrator = Filament::auth()->user();

        return $administrator instanceof User ? $administrator : null;
    }

    private static function mayManageRolesOf(User $user): bool
    {
        $administrator = self::administrator();

        return $administrator instanceof User
            && Gate::forUser($administrator)->allows('manageRoles', $user);
    }

    private function userLabel(User $user): string
    {
        return "{$user->name} ({$user->email})";
    }

    private function notifyFailure(AdminAuthorizationException $adminAuthorizationException): void
    {
        Notification::make()
            ->title($adminAuthorizationException->failure->message())
            ->danger()
            ->send();
    }
}

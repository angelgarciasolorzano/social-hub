<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Filament\Concerns\ConfirmsCurrentPassword;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Resources\Users\Widgets\UserActivityOverview;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use App\User\Modules\AccountStatus\Services\AccountStatusService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use LogicException;
use Override;

class ViewUser extends ViewRecord
{
    use ConfirmsCurrentPassword;

    #[Override]
    protected static string $resource = UserResource::class;

    private const string SUSPEND_ACTION = 'suspendAccount';

    private const string REACTIVATE_ACTION = 'reactivateAccount';

    private const string MANAGE_ROLES_ACTION = 'manageRoles';

    private const string ROLES_FIELD = 'roles';

    private const string ROLES_PASSWORD_THROTTLE_PREFIX = 'admin-users:manage-roles:';

    /**
     * @return Action[]
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make(self::MANAGE_ROLES_ACTION)
                ->label(TranslatedText::of('admin/roles.actions.manage_roles.label'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray')
                ->authorize('manageRoles')
                ->modalHeading(fn (User $record): string => TranslatedText::of('admin/roles.actions.manage_roles.modal_heading', [
                    'name' => $record->name,
                ]))
                ->modalDescription(TranslatedText::of('admin/roles.actions.manage_roles.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/roles.actions.manage_roles.submit'))
                ->modalWidth('3xl')
                ->fillForm(fn (User $record): array => [self::ROLES_FIELD => $this->adminRoleNamesOf($record)])
                ->schema(fn (): array => $this->manageRolesSchema())
                ->action(fn (array $data, AdminAuthorizationService $adminAuthorizationService) => $this->manageRoles($data, $adminAuthorizationService)),
            Action::make(self::SUSPEND_ACTION)
                ->label(TranslatedText::of('admin/users.actions.suspend.label'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(TranslatedText::of('admin/users.actions.suspend.modal_heading'))
                ->modalDescription(TranslatedText::of('admin/users.actions.suspend.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/users.actions.suspend.submit'))
                ->successNotificationTitle(TranslatedText::of('admin/users.actions.suspend.success'))
                ->failureNotificationTitle(TranslatedText::of('admin/users.actions.suspend.failure'))
                ->authorize('suspend')
                ->schema([
                    Textarea::make('reason')
                        ->label(TranslatedText::of('admin/common.fields.reason'))
                        ->required()
                        ->maxLength(AccountStatusChange::REASON_MAX_LENGTH)
                        ->rows(4),
                ])
                ->action(fn (array $data, Action $action, AccountStatusService $accountStatusService) => $this->changeAccountStatus($data, $action, $accountStatusService, AccountStatus::Suspended)),
            Action::make(self::REACTIVATE_ACTION)
                ->label(TranslatedText::of('admin/users.actions.reactivate.label'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(TranslatedText::of('admin/users.actions.reactivate.modal_heading'))
                ->modalDescription(TranslatedText::of('admin/users.actions.reactivate.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/users.actions.reactivate.submit'))
                ->successNotificationTitle(TranslatedText::of('admin/users.actions.reactivate.success'))
                ->failureNotificationTitle(TranslatedText::of('admin/users.actions.reactivate.failure'))
                ->authorize('reactivate')
                ->schema([
                    Textarea::make('reason')
                        ->label(TranslatedText::of('admin/common.fields.reason'))
                        ->required()
                        ->maxLength(AccountStatusChange::REASON_MAX_LENGTH)
                        ->rows(4),
                ])
                ->action(fn (array $data, Action $action, AccountStatusService $accountStatusService) => $this->changeAccountStatus($data, $action, $accountStatusService, AccountStatus::Active)),
        ];
    }

    /**
     * Filament checks the action's authorization again before running it, and `UserPolicy` denies
     * suspending a suspended account (or reactivating an active one), so when another tab changed the
     * status while this modal was open the call would be dropped without a word. Tell the administrator
     * instead, with the action's own failure notification, and close the modal without writing.
     *
     * @param  array<string, mixed>  $arguments
     */
    #[Override]
    public function callMountedAction(array $arguments = []): mixed
    {
        $action = $this->getMountedAction();

        if ($action instanceof Action && $this->accountAlreadyHasTargetStatus($action)) {
            $action->sendFailureNotification();
            $this->unmountAction();

            return null;
        }

        return parent::callMountedAction($arguments);
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            UserActivityOverview::class,
        ];
    }

    /**
     * Whether the action changes the account status and the account already has the status it sets.
     */
    private function accountAlreadyHasTargetStatus(Action $action): bool
    {
        $targetStatus = match ($action->getName()) {
            self::SUSPEND_ACTION => AccountStatus::Suspended,
            self::REACTIVATE_ACTION => AccountStatus::Active,
            default => null,
        };

        $model = $this->getRecord();

        return $targetStatus instanceof AccountStatus
            && $model instanceof User
            && $model->status === $targetStatus;
    }

    /**
     * Apply the status change requested from a header action; a change made meanwhile
     * from another tab marks the action as failed so its failure notification is sent.
     *
     * @param  array<mixed>  $data
     */
    private function changeAccountStatus(array $data, Action $action, AccountStatusService $accountStatusService, AccountStatus $accountStatus): void
    {
        $administrator = Filament::auth()->user();
        $model = $this->getRecord();

        $reason = $data['reason'] ?? null;

        abort_unless($administrator instanceof User && $model instanceof User, 403);
        abort_unless(\is_string($reason), 422);

        try {
            match ($accountStatus) {
                AccountStatus::Suspended => $accountStatusService->suspend($model, $administrator, $reason),
                AccountStatus::Active => $accountStatusService->reactivate($model, $administrator, $reason),
            };
        } catch (LogicException) {
            $action->failure();
        }
    }

    /**
     * Confirm the password, leave the user with the selected cataloged roles and report what changed,
     * or why nothing did.
     *
     * @param  array<mixed>  $data
     *
     * @throws ValidationException when the password is wrong or its attempts are exhausted
     */
    private function manageRoles(array $data, AdminAuthorizationService $adminAuthorizationService): void
    {
        $administrator = Filament::auth()->user();
        $model = $this->getRecord();
        $currentPassword = $data[self::CURRENT_PASSWORD_FIELD] ?? null;

        abort_unless($administrator instanceof User && $model instanceof User, 403);
        abort_unless(\is_string($currentPassword), 422);

        self::ensureCurrentPassword(
            $administrator,
            $currentPassword,
            self::ROLES_PASSWORD_THROTTLE_PREFIX,
            'mountedActions.0.data.'.self::CURRENT_PASSWORD_FIELD,
        );

        $selectedRoleNames = $this->selectedRoleNames($data[self::ROLES_FIELD] ?? []);
        $selectedRoles = array_values(array_filter(
            $this->adminRoles(),
            static fn (Role $role): bool => \in_array($role->name, $selectedRoleNames, true),
        ));

        try {
            $adminAuthorizationChanges = $adminAuthorizationService->syncUserRoles($model, $selectedRoles, $administrator);
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            Notification::make()
                ->title($adminAuthorizationException->failure->message())
                ->danger()
                ->send();

            return;
        }

        $changeCount = $adminAuthorizationChanges->changeCount();

        Notification::make()
            ->title($adminAuthorizationChanges->hasChanges()
                ? trans_choice('admin/roles.actions.manage_roles.success', $changeCount, ['count' => $changeCount])
                : TranslatedText::of('admin/roles.actions.manage_roles.unchanged'))
            ->success()
            ->send();
    }

    /**
     * Every role of the `admin` guard, system or custom, with its description, a live summary of what
     * saving would assign and remove, and the current password.
     *
     * @return list<Component>
     */
    private function manageRolesSchema(): array
    {
        $options = [];
        $descriptions = [];

        foreach ($this->adminRoles() as $role) {
            $options[$role->name] = $role->displayName();
            $descriptions[$role->name] = $role->displayDescription() ?? '';
        }

        return [
            CheckboxList::make(self::ROLES_FIELD)
                ->label(TranslatedText::of('admin/roles.actions.manage_roles.field'))
                ->options($options)
                ->descriptions($descriptions)
                ->columns(2)
                ->bulkToggleable()
                ->live(),
            Section::make(TranslatedText::of('admin/roles.summary.heading'))
                ->compact()
                ->schema([
                    Text::make(fn (Get $get, User $record): string => $this->roleChangeSummary($this->adminRoleNamesOf($record), $this->selectedRoleNames($get(self::ROLES_FIELD)))),
                ]),
            self::currentPasswordField(),
        ];
    }

    /**
     * What saving would assign and remove, with the shown role names, or a sentence saying nothing changes.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function roleChangeSummary(array $before, array $after): string
    {
        $displayNames = [];

        foreach ($this->adminRoles() as $role) {
            $displayNames[$role->name] = $role->displayName();
        }

        $assigned = array_values(array_filter(array_keys($displayNames), static fn (string $roleName): bool => \in_array($roleName, $after, true) && ! \in_array($roleName, $before, true)));
        $removed = array_values(array_filter(array_keys($displayNames), static fn (string $roleName): bool => \in_array($roleName, $before, true) && ! \in_array($roleName, $after, true)));

        if ($assigned === [] && $removed === []) {
            return TranslatedText::of('admin/roles.actions.manage_roles.summary_none');
        }

        $sentences = [];

        foreach (['summary_grant' => $assigned, 'summary_revoke' => $removed] as $key => $roleNames) {
            if ($roleNames !== []) {
                $sentences[] = trans_choice('admin/roles.actions.manage_roles.'.$key, \count($roleNames), [
                    'count' => \count($roleNames),
                    'list' => implode(', ', array_map(static fn (string $roleName): string => $displayNames[$roleName], $roleNames)),
                ]);
            }
        }

        return implode('. ', $sentences).'.';
    }

    /**
     * The roles of the `admin` guard in the order the panel lists them.
     *
     * @return list<Role>
     */
    private function adminRoles(): array
    {
        return Role::inDisplayOrder(Role::query()->where('guard_name', Role::GUARD_NAME)->get());
    }

    /**
     * The names of the user's roles of the `admin` guard, as the form's initial state.
     *
     * @return list<string>
     */
    private function adminRoleNamesOf(User $user): array
    {
        $roleNames = [];

        foreach ($user->roles as $role) {
            if ($role->guard_name === Role::GUARD_NAME) {
                $roleNames[] = $role->name;
            }
        }

        return $roleNames;
    }

    /**
     * The submitted role names; anything that is not a string is ignored.
     *
     * @return list<string>
     */
    private function selectedRoleNames(mixed $selectedRoleNames): array
    {
        return array_values(array_filter(
            \is_array($selectedRoleNames) ? $selectedRoleNames : [],
            \is_string(...),
        ));
    }
}

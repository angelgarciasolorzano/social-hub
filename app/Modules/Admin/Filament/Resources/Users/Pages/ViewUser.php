<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Filament\Concerns\ConfirmsCurrentPassword;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Resources\Users\Widgets\UserActivityOverview;
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
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use LogicException;
use Override;
use Spatie\Permission\Models\Role;

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
                ->fillForm(fn (User $record): array => [self::ROLES_FIELD => $this->catalogRoleNames($record)])
                ->schema([
                    CheckboxList::make(self::ROLES_FIELD)
                        ->label(TranslatedText::of('admin/roles.actions.manage_roles.field'))
                        ->options($this->roleOptions())
                        ->descriptions($this->roleDescriptions())
                        ->columns(2)
                        ->bulkToggleable(),
                    self::currentPasswordField(),
                ])
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
        $selectedRoleNames = $data[self::ROLES_FIELD] ?? [];

        abort_unless($administrator instanceof User && $model instanceof User, 403);
        abort_unless(\is_string($currentPassword) && \is_array($selectedRoleNames), 422);

        self::ensureCurrentPassword(
            $administrator,
            $currentPassword,
            self::ROLES_PASSWORD_THROTTLE_PREFIX,
            'mountedActions.0.data.'.self::CURRENT_PASSWORD_FIELD,
        );

        $selectedRoles = array_values(array_filter(array_map(
            static fn (mixed $roleName): ?AdminRole => \is_string($roleName) ? AdminRole::tryFrom($roleName) : null,
            $selectedRoleNames,
        )));

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
     * The cataloged roles of the user, as the form's initial state.
     *
     * @return list<string>
     */
    private function catalogRoleNames(User $user): array
    {
        $roleNames = $user->roles
            ->filter(static fn (Role $role): bool => $role->guard_name === 'admin')
            ->map(static fn (Role $role): string => $role->name)
            ->all();

        return array_values(array_filter(
            array_map(static fn (AdminRole $adminRole): string => $adminRole->value, AdminRole::cases()),
            static fn (string $roleName): bool => \in_array($roleName, $roleNames, true),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        $roleOptions = [];

        foreach (AdminRole::cases() as $adminRole) {
            $roleOptions[$adminRole->value] = $adminRole->label();
        }

        return $roleOptions;
    }

    /**
     * @return array<string, string>
     */
    private function roleDescriptions(): array
    {
        $roleDescriptions = [];

        foreach (AdminRole::cases() as $adminRole) {
            $roleDescriptions[$adminRole->value] = $adminRole->description();
        }

        return $roleDescriptions;
    }
}

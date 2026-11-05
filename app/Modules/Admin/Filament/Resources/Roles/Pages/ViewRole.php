<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Filament\Concerns\ConfirmsCurrentPassword;
use App\Modules\Admin\Filament\Concerns\EditsRolePermissions;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Override;

class ViewRole extends ViewRecord
{
    use ConfirmsCurrentPassword;
    use EditsRolePermissions;

    #[Override]
    protected static string $resource = RoleResource::class;

    private const string EDIT_PERMISSIONS_ACTION = 'editPermissions';

    private const string PASSWORD_THROTTLE_PREFIX = 'admin-roles:edit-permissions:';

    private const string DUPLICATE_PASSWORD_THROTTLE_PREFIX = 'admin-roles:duplicate:';

    private const string DELETE_PASSWORD_THROTTLE_PREFIX = 'admin-roles:delete:';

    private const string LABEL_FIELD = 'label';

    private const string DESCRIPTION_FIELD = 'description';

    private const string CURRENT_PASSWORD_ERROR_KEY = 'mountedActions.0.data.current_password';

    /**
     * @return Action[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make(self::EDIT_PERMISSIONS_ACTION)
                ->label(TranslatedText::of('admin/roles.actions.edit_permissions.label'))
                ->icon(Heroicon::OutlinedKey)
                ->authorize('update')
                ->modalHeading(fn (Role $record): string => TranslatedText::of('admin/roles.actions.edit_permissions.modal_heading', [
                    'role' => $record->displayName(),
                ]))
                ->modalDescription(TranslatedText::of('admin/roles.actions.edit_permissions.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/roles.actions.edit_permissions.submit'))
                ->modalWidth('3xl')
                ->fillForm(fn (Role $record): array => [self::PERMISSIONS_FIELD => self::permissionsByArea($record)])
                ->schema([
                    Text::make(TranslatedText::of('admin/roles.form.dependencies_note')),
                    ...self::permissionCheckboxLists(),
                    Section::make(TranslatedText::of('admin/roles.summary.heading'))
                        ->compact()
                        ->schema([self::permissionChangeSummaryText()]),
                    self::currentPasswordField(),
                ])
                ->action(fn (array $data, AdminAuthorizationService $adminAuthorizationService) => $this->editPermissions($data, $adminAuthorizationService)),
            Action::make('editDetails')
                ->label(TranslatedText::of('admin/roles.actions.edit_details.label'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->authorize('updateDetails')
                ->modalHeading(fn (Role $record): string => TranslatedText::of('admin/roles.actions.edit_details.modal_heading', [
                    'role' => $record->displayName(),
                ]))
                ->modalSubmitActionLabel(TranslatedText::of('admin/roles.actions.edit_details.submit'))
                ->fillForm(fn (Role $record): array => [
                    self::LABEL_FIELD => $record->label,
                    self::DESCRIPTION_FIELD => $record->description,
                ])
                ->schema([$this->labelField(), $this->descriptionField()])
                ->action(fn (array $data, AdminAuthorizationService $adminAuthorizationService) => $this->editDetails($data, $adminAuthorizationService)),
            Action::make('duplicate')
                ->label(TranslatedText::of('admin/roles.actions.duplicate.label'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->authorize('replicate')
                ->modalHeading(fn (Role $record): string => TranslatedText::of('admin/roles.actions.duplicate.modal_heading', [
                    'role' => $record->displayName(),
                ]))
                ->modalDescription(TranslatedText::of('admin/roles.actions.duplicate.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/roles.actions.duplicate.submit'))
                ->fillForm(fn (Role $record): array => [
                    self::LABEL_FIELD => TranslatedText::of('admin/roles.actions.duplicate.copy_label', ['role' => $record->displayName()]),
                ])
                ->schema([$this->labelField(), self::currentPasswordField()])
                ->action(fn (array $data, AdminAuthorizationService $adminAuthorizationService) => $this->duplicate($data, $adminAuthorizationService)),
            Action::make('delete')
                ->label(TranslatedText::of('admin/roles.actions.delete.label'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->authorize('delete')
                ->disabled(fn (Role $record): bool => $this->userCount($record) > 0)
                ->tooltip(fn (Role $record): ?string => $this->userCount($record) > 0 ? $this->blockedByUsersMessage($record) : null)
                ->modalHeading(fn (Role $record): string => TranslatedText::of('admin/roles.actions.delete.modal_heading', [
                    'role' => $record->displayName(),
                ]))
                ->modalDescription(TranslatedText::of('admin/roles.actions.delete.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/roles.actions.delete.submit'))
                ->modalIcon(Heroicon::OutlinedTrash)
                ->schema([self::currentPasswordField()])
                ->action(fn (array $data, AdminAuthorizationService $adminAuthorizationService) => $this->deleteRole($data, $adminAuthorizationService)),
        ];
    }

    /**
     * Save the label and description of a custom role and report whether they changed.
     *
     * @param  array<mixed>  $data
     */
    private function editDetails(array $data, AdminAuthorizationService $adminAuthorizationService): void
    {
        [$administrator, $role] = $this->administratorAndRole();
        $label = $data[self::LABEL_FIELD] ?? null;
        $description = $data[self::DESCRIPTION_FIELD] ?? null;

        abort_unless(\is_string($label) && ($description === null || \is_string($description)), 422);

        try {
            $changed = $adminAuthorizationService->updateRoleDetails($role, $label, $description, $administrator);
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            $this->notifyFailure($adminAuthorizationException);

            return;
        }

        $role->refresh();

        Notification::make()
            ->title($changed
                ? TranslatedText::of('admin/roles.actions.edit_details.success', ['role' => $role->displayName()])
                : TranslatedText::of('admin/roles.actions.edit_details.unchanged'))
            ->success()
            ->send();
    }

    /**
     * Confirm the password, copy the role under the given label and open the copy.
     *
     * @param  array<mixed>  $data
     *
     * @throws ValidationException when the password is wrong or its attempts are exhausted
     */
    private function duplicate(array $data, AdminAuthorizationService $adminAuthorizationService): void
    {
        [$administrator, $role] = $this->administratorAndRole();
        $label = $data[self::LABEL_FIELD] ?? null;

        abort_unless(\is_string($label), 422);

        $this->confirmPassword($administrator, $data, self::DUPLICATE_PASSWORD_THROTTLE_PREFIX);

        try {
            $copy = $adminAuthorizationService->duplicateRole($role, $label, $administrator);
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            $this->notifyFailure($adminAuthorizationException);

            return;
        }

        Notification::make()
            ->title(TranslatedText::of('admin/roles.actions.duplicate.success', ['role' => $copy->displayName()]))
            ->success()
            ->send();

        $this->redirect(RoleResource::getUrl('view', ['record' => $copy]));
    }

    /**
     * Confirm the password, delete the custom role and go back to the list.
     *
     * @param  array<mixed>  $data
     *
     * @throws ValidationException when the password is wrong or its attempts are exhausted
     */
    private function deleteRole(array $data, AdminAuthorizationService $adminAuthorizationService): void
    {
        [$administrator, $role] = $this->administratorAndRole();

        $this->confirmPassword($administrator, $data, self::DELETE_PASSWORD_THROTTLE_PREFIX);

        $roleName = $role->displayName();

        try {
            $adminAuthorizationService->deleteRole($role, $administrator);
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            $this->notifyFailure($adminAuthorizationException);

            return;
        }

        Notification::make()
            ->title(TranslatedText::of('admin/roles.actions.delete.success', ['role' => $roleName]))
            ->success()
            ->send();

        $this->redirect(RoleResource::getUrl('index'));
    }

    /**
     * @return array{0: User, 1: Role}
     */
    private function administratorAndRole(): array
    {
        $administrator = Filament::auth()->user();
        $model = $this->getRecord();

        abort_unless($administrator instanceof User && $model instanceof Role, 403);

        return [$administrator, $model];
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws ValidationException when the password is wrong or its attempts are exhausted
     */
    private function confirmPassword(User $administrator, array $data, string $throttlePrefix): void
    {
        $currentPassword = $data[self::CURRENT_PASSWORD_FIELD] ?? null;

        abort_unless(\is_string($currentPassword), 422);

        self::ensureCurrentPassword($administrator, $currentPassword, $throttlePrefix, self::CURRENT_PASSWORD_ERROR_KEY);
    }

    private function notifyFailure(AdminAuthorizationException $adminAuthorizationException): void
    {
        Notification::make()
            ->title($adminAuthorizationException->failure->message())
            ->danger()
            ->send();
    }

    private function labelField(): TextInput
    {
        return TextInput::make(self::LABEL_FIELD)
            ->label(TranslatedText::of('admin/roles.form.label'))
            ->helperText(TranslatedText::of('admin/roles.form.label_helper'))
            ->required()
            ->maxLength(Role::LABEL_MAX_LENGTH);
    }

    private function descriptionField(): Textarea
    {
        return Textarea::make(self::DESCRIPTION_FIELD)
            ->label(TranslatedText::of('admin/roles.form.description'))
            ->helperText(TranslatedText::of('admin/roles.form.description_helper'))
            ->rows(2)
            ->maxLength(Role::DESCRIPTION_MAX_LENGTH);
    }

    private function userCount(Role $role): int
    {
        return $role->users()->count();
    }

    private function blockedByUsersMessage(Role $role): string
    {
        $userCount = $this->userCount($role);

        return trans_choice('admin/roles.actions.delete.blocked_by_users', $userCount, ['count' => $userCount]);
    }

    /**
     * Confirm the password, leave the role with the selected permissions and report what changed, or
     * why nothing did.
     *
     * @param  array<mixed>  $data
     *
     * @throws ValidationException when the password is wrong or its attempts are exhausted
     */
    private function editPermissions(array $data, AdminAuthorizationService $adminAuthorizationService): void
    {
        [$administrator, $role] = $this->administratorAndRole();

        $this->confirmPassword($administrator, $data, self::PASSWORD_THROTTLE_PREFIX);

        try {
            $adminAuthorizationChanges = $adminAuthorizationService->syncRolePermissions(
                $role,
                self::selectedPermissions($data[self::PERMISSIONS_FIELD] ?? []),
                $administrator,
            );
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            $this->notifyFailure($adminAuthorizationException);

            return;
        }

        $role->unsetRelation('permissions');

        $changeCount = $adminAuthorizationChanges->changeCount();

        Notification::make()
            ->title($adminAuthorizationChanges->hasChanges()
                ? trans_choice('admin/roles.actions.edit_permissions.success', $changeCount, ['count' => $changeCount])
                : TranslatedText::of('admin/roles.actions.edit_permissions.unchanged'))
            ->success()
            ->send();
    }
}

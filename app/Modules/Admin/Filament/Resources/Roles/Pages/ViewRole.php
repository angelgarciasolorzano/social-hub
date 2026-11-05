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
        ];
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
        $administrator = Filament::auth()->user();
        $model = $this->getRecord();
        $currentPassword = $data[self::CURRENT_PASSWORD_FIELD] ?? null;

        abort_unless($administrator instanceof User && $model instanceof Role, 403);
        abort_unless(\is_string($currentPassword), 422);

        self::ensureCurrentPassword(
            $administrator,
            $currentPassword,
            self::PASSWORD_THROTTLE_PREFIX,
            'mountedActions.0.data.'.self::CURRENT_PASSWORD_FIELD,
        );

        try {
            $adminAuthorizationChanges = $adminAuthorizationService->syncRolePermissions(
                $model,
                self::selectedPermissions($data[self::PERMISSIONS_FIELD] ?? []),
                $administrator,
            );
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            Notification::make()
                ->title($adminAuthorizationException->failure->message())
                ->danger()
                ->send();

            return;
        }

        $model->unsetRelation('permissions');

        $changeCount = $adminAuthorizationChanges->changeCount();

        Notification::make()
            ->title($adminAuthorizationChanges->hasChanges()
                ? trans_choice('admin/roles.actions.edit_permissions.success', $changeCount, ['count' => $changeCount])
                : TranslatedText::of('admin/roles.actions.edit_permissions.unchanged'))
            ->success()
            ->send();
    }
}

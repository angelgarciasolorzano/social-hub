<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Filament\Concerns\ConfirmsCurrentPassword;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Override;
use Spatie\Permission\Models\Permission;

class ViewRole extends ViewRecord
{
    use ConfirmsCurrentPassword;

    #[Override]
    protected static string $resource = RoleResource::class;

    private const string EDIT_PERMISSIONS_ACTION = 'editPermissions';

    private const string PERMISSIONS_FIELD = 'permissions';

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
                    'role' => AdminRole::tryFrom($record->name)?->label() ?? $record->name,
                ]))
                ->modalDescription(TranslatedText::of('admin/roles.actions.edit_permissions.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/roles.actions.edit_permissions.submit'))
                ->modalWidth('3xl')
                ->fillForm(fn (Role $record): array => [self::PERMISSIONS_FIELD => $this->permissionsByArea($record)])
                ->schema([
                    ...array_map($this->areaCheckboxList(...), $this->areas()),
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
        $adminRole = $model instanceof Role ? AdminRole::tryFrom($model->name) : null;
        $currentPassword = $data[self::CURRENT_PASSWORD_FIELD] ?? null;

        abort_unless($administrator instanceof User && $adminRole instanceof AdminRole, 403);
        abort_unless(\is_string($currentPassword), 422);

        self::ensureCurrentPassword(
            $administrator,
            $currentPassword,
            self::PASSWORD_THROTTLE_PREFIX,
            'mountedActions.0.data.'.self::CURRENT_PASSWORD_FIELD,
        );

        try {
            $adminAuthorizationChanges = $adminAuthorizationService->syncRolePermissions(
                $adminRole,
                $this->selectedPermissions($data[self::PERMISSIONS_FIELD] ?? []),
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

    /**
     * The panel areas of the catalog, in catalog order.
     *
     * @return list<string>
     */
    private function areas(): array
    {
        return array_values(array_unique(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->area(),
            AdminPermission::cases(),
        )));
    }

    /**
     * The checkboxes of one area, with each permission's description under its name.
     */
    private function areaCheckboxList(string $area): CheckboxList
    {
        $adminPermissions = array_filter(
            AdminPermission::cases(),
            static fn (AdminPermission $adminPermission): bool => $adminPermission->area() === $area,
        );

        $options = [];
        $descriptions = [];

        foreach ($adminPermissions as $adminPermission) {
            $options[$adminPermission->value] = $adminPermission->label();
            $descriptions[$adminPermission->value] = $adminPermission->description();
        }

        return CheckboxList::make(self::PERMISSIONS_FIELD.'.'.$area)
            ->label(TranslatedText::of('admin/roles.catalog.areas.'.$area))
            ->options($options)
            ->descriptions($descriptions)
            ->columns(2)
            ->bulkToggleable();
    }

    /**
     * The cataloged permissions the role grants, grouped by area, as the form's initial state.
     *
     * @return array<string, list<string>>
     */
    private function permissionsByArea(Role $role): array
    {
        $grantedNames = $role->permissions
            ->map(static fn (Permission $permission): string => $permission->name)
            ->all();
        $permissionsByArea = array_fill_keys($this->areas(), []);

        foreach (AdminPermission::cases() as $adminPermission) {
            if (\in_array($adminPermission->value, $grantedNames, true)) {
                $permissionsByArea[$adminPermission->area()][] = $adminPermission->value;
            }
        }

        return $permissionsByArea;
    }

    /**
     * The cataloged permissions selected in every area; anything else in the submitted state is ignored.
     *
     * @return list<AdminPermission>
     */
    private function selectedPermissions(mixed $permissionsByArea): array
    {
        if (! \is_array($permissionsByArea)) {
            return [];
        }

        $selectedPermissions = [];

        foreach ($permissionsByArea as $permissions) {
            foreach (\is_array($permissions) ? $permissions : [] as $permissionName) {
                $adminPermission = \is_string($permissionName) ? AdminPermission::tryFrom($permissionName) : null;

                if ($adminPermission instanceof AdminPermission) {
                    $selectedPermissions[] = $adminPermission;
                }
            }
        }

        return $selectedPermissions;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Filament\Concerns\ConfirmsCurrentPassword;
use App\Modules\Admin\Filament\Concerns\EditsRolePermissions;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\RolesOverview;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\UsersPerRoleChart;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Validation\ValidationException;
use Override;

class ListRoles extends ListRecords
{
    use ConfirmsCurrentPassword;
    use EditsRolePermissions;

    #[Override]
    protected static string $resource = RoleResource::class;

    private const string CREATE_PASSWORD_THROTTLE_PREFIX = 'admin-roles:create:';

    private const string LABEL_FIELD = 'label';

    private const string DESCRIPTION_FIELD = 'description';

    /**
     * @return Action[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createRole')
                ->label(TranslatedText::of('admin/roles.actions.create.label'))
                ->icon(Heroicon::OutlinedPlus)
                ->authorize('create')
                ->modalHeading(TranslatedText::of('admin/roles.actions.create.modal_heading'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/roles.actions.create.submit'))
                ->modalWidth('3xl')
                ->fillForm(fn (): array => [self::PERMISSIONS_FIELD => self::permissionsByArea(null)])
                ->schema([
                    TextInput::make(self::LABEL_FIELD)
                        ->label(TranslatedText::of('admin/roles.form.label'))
                        ->helperText(TranslatedText::of('admin/roles.form.label_helper'))
                        ->required()
                        ->maxLength(Role::LABEL_MAX_LENGTH),
                    Textarea::make(self::DESCRIPTION_FIELD)
                        ->label(TranslatedText::of('admin/roles.form.description'))
                        ->helperText(TranslatedText::of('admin/roles.form.description_helper'))
                        ->rows(2)
                        ->maxLength(Role::DESCRIPTION_MAX_LENGTH),
                    Section::make(TranslatedText::of('admin/roles.form.permissions'))
                        ->description(TranslatedText::of('admin/roles.form.permissions_helper'))
                        ->schema([
                            Text::make(TranslatedText::of('admin/roles.form.dependencies_note')),
                            ...self::permissionCheckboxLists(),
                        ]),
                    Section::make(TranslatedText::of('admin/roles.summary.heading'))
                        ->compact()
                        ->schema([self::permissionChangeSummaryText()]),
                    self::currentPasswordField(),
                ])
                ->action(fn (array $data, AdminAuthorizationService $adminAuthorizationService) => $this->createRole($data, $adminAuthorizationService)),
            Action::make('openMatrix')
                ->label(TranslatedText::of('admin/roles.actions.open_matrix.label'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(RoleResource::getUrl('matrix')),
        ];
    }

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            RolesOverview::class,
            UsersPerRoleChart::class,
        ];
    }

    /**
     * Confirm the password, create the custom role and open it. A taken label is reported on its field.
     *
     * @param  array<mixed>  $data
     *
     * @throws ValidationException when the password is wrong or the label is taken
     */
    private function createRole(array $data, AdminAuthorizationService $adminAuthorizationService): void
    {
        $administrator = Filament::auth()->user();
        $label = $data[self::LABEL_FIELD] ?? null;
        $description = $data[self::DESCRIPTION_FIELD] ?? null;
        $currentPassword = $data[self::CURRENT_PASSWORD_FIELD] ?? null;

        abort_unless($administrator instanceof User, 403);
        abort_unless(\is_string($label) && ($description === null || \is_string($description)) && \is_string($currentPassword), 422);

        self::ensureCurrentPassword(
            $administrator,
            $currentPassword,
            self::CREATE_PASSWORD_THROTTLE_PREFIX,
            'mountedActions.0.data.'.self::CURRENT_PASSWORD_FIELD,
        );

        try {
            $role = $adminAuthorizationService->createRole(
                $label,
                $description,
                self::selectedPermissions($data[self::PERMISSIONS_FIELD] ?? []),
                $administrator,
            );
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            if ($adminAuthorizationException->failure === AdminAuthorizationFailure::RoleLabelTaken) {
                throw ValidationException::withMessages([
                    'mountedActions.0.data.'.self::LABEL_FIELD => $adminAuthorizationException->failure->message(),
                ]);
            }

            Notification::make()
                ->title($adminAuthorizationException->failure->message())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(TranslatedText::of('admin/roles.actions.create.success', ['role' => $role->displayName()]))
            ->success()
            ->send();

        $this->redirect(RoleResource::getUrl('view', ['record' => $role]));
    }
}

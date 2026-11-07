<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Pages;

use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Filament\Concerns\ConfirmsCurrentPassword;
use App\Modules\Admin\Filament\Concerns\EditsRolePermissions;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Override;

/**
 * Edit a role on one page: the name and description of a custom role and the permissions of any role
 * but the locked `admin`, with the live summary beside them and the current password. Both changes are
 * saved together: if one is refused, nothing changes.
 */
class EditRole extends EditRecord
{
    use ConfirmsCurrentPassword;
    use EditsRolePermissions;

    private const string EDIT_PASSWORD_THROTTLE_PREFIX = 'admin-roles:edit:';

    private const string LABEL_FIELD = 'label';

    private const string DESCRIPTION_FIELD = 'description';

    #[Override]
    protected static string $resource = RoleResource::class;

    /**
     * Whether the last save changed anything, for its notification.
     */
    private bool $savedWithChanges = false;

    #[Override]
    public function getTitle(): string|Htmlable
    {
        return TranslatedText::of('admin/roles.pages.edit.title', ['role' => $this->role()->displayName()]);
    }

    #[Override]
    public function getBreadcrumb(): string
    {
        return TranslatedText::of('admin/roles.pages.edit.breadcrumb');
    }

    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(['default' => 1, 'lg' => 2])
                            ->schema([
                                Section::make(TranslatedText::of('admin/roles.wizard.details.label'))
                                    ->schema($this->detailsSchema()),
                                Section::make(TranslatedText::of('admin/roles.form.permissions'))
                                    ->description(TranslatedText::of('admin/roles.form.permissions_helper'))
                                    ->schema([
                                        Text::make(TranslatedText::of('admin/roles.form.dependencies_note')),
                                        ...self::permissionCheckboxLists(),
                                    ]),
                            ]),
                        Grid::make(1)
                            ->schema([
                                Section::make(TranslatedText::of('admin/roles.summary.heading'))
                                    ->compact()
                                    ->schema(self::permissionSummarySchema()),
                                self::sensitiveAcknowledgementField(),
                                self::currentPasswordField(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Start from the role's own permissions.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data[self::PERMISSIONS_FIELD] = self::permissionsByArea($this->role());

        return $data;
    }

    /**
     * Confirm the password and save the details (only of a custom role) and the permissions in one
     * transaction. A taken label is reported on its field; any other refusal is notified.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the password is wrong or the label is taken
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $role = $this->role();
        $administrator = Filament::auth()->user();
        $currentPassword = $data[self::CURRENT_PASSWORD_FIELD] ?? null;

        abort_unless($administrator instanceof User, 403);
        abort_unless(\is_string($currentPassword), 422);

        self::ensureCurrentPassword(
            $administrator,
            $currentPassword,
            self::EDIT_PASSWORD_THROTTLE_PREFIX,
            'data.'.self::CURRENT_PASSWORD_FIELD,
        );

        $adminAuthorizationService = resolve(AdminAuthorizationService::class);
        $canEditDetails = Gate::forUser($administrator)->allows('updateDetails', $role);

        try {
            $this->savedWithChanges = DB::transaction(function () use ($data, $role, $administrator, $adminAuthorizationService, $canEditDetails): bool {
                $detailsChanged = $canEditDetails && $this->saveDetails($data, $role, $administrator, $adminAuthorizationService);
                $adminAuthorizationChanges = $adminAuthorizationService->syncRolePermissions(
                    $role,
                    self::selectedPermissions($data[self::PERMISSIONS_FIELD] ?? []),
                    $administrator,
                );

                return $detailsChanged || $adminAuthorizationChanges->hasChanges();
            });
        } catch (AdminAuthorizationException $adminAuthorizationException) {
            if ($adminAuthorizationException->failure === AdminAuthorizationFailure::RoleLabelTaken) {
                throw ValidationException::withMessages([
                    'data.'.self::LABEL_FIELD => $adminAuthorizationException->failure->message(),
                ]);
            }

            Notification::make()
                ->title($adminAuthorizationException->failure->message())
                ->danger()
                ->send();

            throw new Halt($adminAuthorizationException->getMessage(), $adminAuthorizationException->getCode(), $adminAuthorizationException);
        }

        $role->refresh()->unsetRelation('permissions');

        return $role;
    }

    #[Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return $this->savedWithChanges
            ? TranslatedText::of('admin/roles.actions.edit.success', ['role' => $this->role()->displayName()])
            : TranslatedText::of('admin/roles.actions.edit.unchanged');
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return RoleResource::getUrl('view', ['record' => $this->role()]);
    }

    /**
     * The name and description: editable on a custom role, the catalog's texts on a system role.
     *
     * @return list<TextInput|Textarea|Text>
     */
    private function detailsSchema(): array
    {
        $role = $this->role();
        $administrator = Filament::auth()->user();

        if ($administrator instanceof User && Gate::forUser($administrator)->allows('updateDetails', $role)) {
            return [
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
            ];
        }

        return [
            Text::make($role->displayName())->weight(FontWeight::SemiBold),
            Text::make($role->displayDescription() ?? ''),
            Text::make(TranslatedText::of('admin/roles.form.read_only_details')),
        ];
    }

    /**
     * Save the label and description of a custom role and say whether they changed.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveDetails(array $data, Role $role, User $administrator, AdminAuthorizationService $adminAuthorizationService): bool
    {
        $label = $data[self::LABEL_FIELD] ?? null;
        $description = $data[self::DESCRIPTION_FIELD] ?? null;

        abort_unless(\is_string($label) && ($description === null || \is_string($description)), 422);

        return $adminAuthorizationService->updateRoleDetails($role, $label, $description, $administrator);
    }

    private function role(): Role
    {
        $model = $this->getRecord();

        if ($model instanceof Role) {
            return $model;
        }

        abort(404);
    }
}

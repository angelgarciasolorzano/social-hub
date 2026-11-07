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
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Override;

/**
 * Create a custom role in three steps: its name and purpose (optionally starting from the permissions
 * of an existing role), its permissions with a live summary beside them, and a review with the current
 * password. The role is written by `AdminAuthorizationService::createRole()`.
 */
class CreateRole extends CreateRecord
{
    use ConfirmsCurrentPassword;
    use EditsRolePermissions;
    use HasWizard;

    private const string CREATE_PASSWORD_THROTTLE_PREFIX = 'admin-roles:create:';

    private const string LABEL_FIELD = 'label';

    private const string DESCRIPTION_FIELD = 'description';

    private const string START_FROM_FIELD = 'start_from';

    /**
     * Keeps the summary in view beside the permissions while they scroll, on wide screens only.
     */
    private const string STICKY_SUMMARY_CLASSES = 'lg:sticky lg:top-24 lg:self-start';

    #[Override]
    protected static string $resource = RoleResource::class;

    #[Override]
    protected static bool $canCreateAnother = false;

    #[Override]
    public function getTitle(): string|Htmlable
    {
        return TranslatedText::of('admin/roles.pages.create.title');
    }

    #[Override]
    public function getBreadcrumb(): string
    {
        return TranslatedText::of('admin/roles.pages.create.breadcrumb');
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return [
            Step::make('details')
                ->label(TranslatedText::of('admin/roles.wizard.details.label'))
                ->description(TranslatedText::of('admin/roles.wizard.details.description'))
                ->schema([
                    TextInput::make(self::LABEL_FIELD)
                        ->label(TranslatedText::of('admin/roles.form.label'))
                        ->helperText(TranslatedText::of('admin/roles.form.label_helper'))
                        ->required()
                        ->maxLength(Role::LABEL_MAX_LENGTH)
                        ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                            if (\is_string($value) && self::labelIsTaken($value)) {
                                $fail(AdminAuthorizationFailure::RoleLabelTaken->message());
                            }
                        }),
                    Textarea::make(self::DESCRIPTION_FIELD)
                        ->label(TranslatedText::of('admin/roles.form.description'))
                        ->helperText(TranslatedText::of('admin/roles.form.description_helper'))
                        ->rows(2)
                        ->maxLength(Role::DESCRIPTION_MAX_LENGTH),
                    Select::make(self::START_FROM_FIELD)
                        ->label(TranslatedText::of('admin/roles.form.start_from'))
                        ->helperText(TranslatedText::of('admin/roles.form.start_from_helper'))
                        ->options($this->startFromOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, mixed $state): mixed => $set(
                            self::PERMISSIONS_FIELD,
                            self::permissionsByArea(\is_string($state) ? self::adminRoleQuery()->where('name', $state)->first() : null),
                        ))
                        ->dehydrated(false),
                ]),
            Step::make('permissions')
                ->label(TranslatedText::of('admin/roles.wizard.permissions.label'))
                ->description(TranslatedText::of('admin/roles.wizard.permissions.description'))
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 3])
                        ->schema([
                            Section::make(TranslatedText::of('admin/roles.form.permissions'))
                                ->description(TranslatedText::of('admin/roles.form.permissions_helper'))
                                ->columnSpan(['default' => 1, 'lg' => 2])
                                ->schema([
                                    Text::make(TranslatedText::of('admin/roles.form.dependencies_note')),
                                    ...self::permissionCheckboxLists(),
                                ]),
                            Section::make(TranslatedText::of('admin/roles.summary.heading'))
                                ->compact()
                                ->extraAttributes(['class' => self::STICKY_SUMMARY_CLASSES])
                                ->schema(self::permissionSummarySchema()),
                        ]),
                ]),
            Step::make('review')
                ->label(TranslatedText::of('admin/roles.wizard.review.label'))
                ->description(TranslatedText::of('admin/roles.wizard.review.description'))
                ->schema([
                    Section::make(TranslatedText::of('admin/roles.summary.heading'))
                        ->schema([
                            Text::make(fn (Get $get): string => \is_string($get(self::LABEL_FIELD)) ? $get(self::LABEL_FIELD) : '')
                                ->weight(FontWeight::SemiBold),
                            Text::make(fn (Get $get): string => \is_string($get(self::DESCRIPTION_FIELD)) ? $get(self::DESCRIPTION_FIELD) : '')
                                ->visible(fn (Get $get): bool => \is_string($get(self::DESCRIPTION_FIELD)) && trim($get(self::DESCRIPTION_FIELD)) !== ''),
                            ...self::permissionSummarySchema(),
                        ]),
                    self::sensitiveAcknowledgementField(),
                    self::currentPasswordField(),
                ]),
        ];
    }

    /**
     * Start with every permission area empty.
     */
    #[Override]
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill([self::PERMISSIONS_FIELD => self::permissionsByArea(null)]);

        $this->callHook('afterFill');
    }

    /**
     * Confirm the password and create the custom role. A label taken meanwhile is reported on its field;
     * any other refusal is notified and nothing is created.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the password is wrong or the label is taken
     */
    #[Override]
    protected function handleRecordCreation(array $data): Role
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
            'data.'.self::CURRENT_PASSWORD_FIELD,
        );

        try {
            return resolve(AdminAuthorizationService::class)->createRole(
                $label,
                $description,
                self::selectedPermissions($data[self::PERMISSIONS_FIELD] ?? []),
                $administrator,
            );
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
    }

    #[Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        $record = $this->getRecord();

        return $record instanceof Role
            ? TranslatedText::of('admin/roles.actions.create.success', ['role' => $record->displayName()])
            : null;
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return RoleResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * The roles of the `admin` guard a new role can start from, in display order, by stored name.
     *
     * @return array<string, string>
     */
    private function startFromOptions(): array
    {
        $options = [];

        foreach (Role::inDisplayOrder(self::adminRoleQuery()->get()) as $role) {
            $options[$role->name] = $role->displayName();
        }

        return $options;
    }

    /**
     * Whether another role of the `admin` guard already uses this label, ignoring case and spaces around.
     */
    private static function labelIsTaken(string $label): bool
    {
        return self::adminRoleQuery()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower(trim($label))])
            ->exists();
    }

    /**
     * @return Builder<Role>
     */
    private static function adminRoleQuery(): Builder
    {
        return Role::query()->where('guard_name', Role::GUARD_NAME);
    }
}

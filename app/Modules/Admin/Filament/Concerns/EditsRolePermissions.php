<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

/**
 * The permission checkboxes of a role form: one list per panel area with each permission's description,
 * kept complete while the administrator edits them (checking a permission checks what it requires,
 * unchecking one unchecks what depends on it, across areas), and a live summary of what will change.
 *
 * The form state lives under `permissions`, keyed by area: `['users' => ['admin.users.viewAny', …], …]`.
 */
trait EditsRolePermissions
{
    protected const string PERMISSIONS_FIELD = 'permissions';

    protected const string ACKNOWLEDGE_SENSITIVE_FIELD = 'acknowledge_sensitive';

    /**
     * One live checkbox list per area, in catalog order.
     *
     * @return list<CheckboxList>
     */
    protected static function permissionCheckboxLists(): array
    {
        return array_map(self::areaCheckboxList(...), self::permissionAreas());
    }

    /**
     * A live text describing what saving would grant and revoke, compared with the role being edited
     * (or with no permissions for a new role).
     */
    protected static function permissionChangeSummaryText(): Text
    {
        return Text::make(fn (Get $get, ?Role $record): string => self::permissionChangeSummary(
            self::selectedPermissions(self::permissionsByArea($record)),
            self::selectedPermissions($get(self::PERMISSIONS_FIELD)),
        ));
    }

    /**
     * The cataloged permissions the role grants, grouped by area, as the form's initial state. A new
     * role starts with every area empty.
     *
     * @return array<string, list<string>>
     */
    protected static function permissionsByArea(?Role $role): array
    {
        $grantedNames = [];

        foreach ($role instanceof Role ? $role->permissions : [] as $permission) {
            $grantedNames[] = $permission->name;
        }

        return self::groupByArea(array_values(array_filter(
            AdminPermission::cases(),
            static fn (AdminPermission $adminPermission): bool => \in_array($adminPermission->value, $grantedNames, true),
        )));
    }

    /**
     * The cataloged permissions selected in every area, in catalog order; anything else in the
     * submitted state is ignored.
     *
     * @return list<AdminPermission>
     */
    protected static function selectedPermissions(mixed $permissionsByArea): array
    {
        $selectedNames = [];

        foreach (\is_array($permissionsByArea) ? $permissionsByArea : [] as $permissionNames) {
            foreach (\is_array($permissionNames) ? $permissionNames : [] as $permissionName) {
                if (\is_string($permissionName)) {
                    $selectedNames[] = $permissionName;
                }
            }
        }

        return array_values(array_filter(
            AdminPermission::cases(),
            static fn (AdminPermission $adminPermission): bool => \in_array($adminPermission->value, $selectedNames, true),
        ));
    }

    /**
     * The selection after one change, kept complete: what was just checked brings its requirements, and
     * what was just unchecked takes its dependents with it.
     *
     * @return array<string, list<string>>
     */
    protected static function applyPermissionDependencies(mixed $permissionsByAreaBefore, mixed $permissionsByAreaAfter): array
    {
        $before = self::selectedPermissions($permissionsByAreaBefore);
        $after = self::selectedPermissions($permissionsByAreaAfter);

        $checked = array_values(array_filter(
            $after,
            static fn (AdminPermission $adminPermission): bool => ! \in_array($adminPermission, $before, true),
        ));

        $unchecked = array_values(array_filter(
            $before,
            static fn (AdminPermission $adminPermission): bool => ! \in_array($adminPermission, $after, true),
        ));

        $selection = AdminPermission::withRequirements([...$after, ...AdminPermission::withRequirements($checked)]);

        foreach ($unchecked as $uncheckedPermission) {
            $removed = [$uncheckedPermission, ...$uncheckedPermission->dependents()];

            $selection = array_values(array_filter(
                $selection,
                static fn (AdminPermission $adminPermission): bool => ! \in_array($adminPermission, $removed, true),
            ));
        }

        return self::groupByArea($selection);
    }

    /**
     * What saving would change, with the translated permission names: the grants, then the revocations,
     * or a sentence saying nothing changes.
     *
     * @param  list<AdminPermission>  $before
     * @param  list<AdminPermission>  $after
     */
    protected static function permissionChangeSummary(array $before, array $after): string
    {
        $granted = array_values(array_filter(
            $after,
            static fn (AdminPermission $adminPermission): bool => ! \in_array($adminPermission, $before, true),
        ));

        $revoked = array_values(array_filter(
            $before,
            static fn (AdminPermission $adminPermission): bool => ! \in_array($adminPermission, $after, true),
        ));

        if ($granted === [] && $revoked === []) {
            return TranslatedText::of('admin/roles.summary.none');
        }

        $sentences = [];

        if ($granted !== []) {
            $sentences[] = trans_choice('admin/roles.summary.grant', \count($granted), [
                'count' => \count($granted),
                'list' => self::permissionLabels($granted),
            ]);
        }

        if ($revoked !== []) {
            $sentences[] = trans_choice('admin/roles.summary.revoke', \count($revoked), [
                'count' => \count($revoked),
                'list' => self::permissionLabels($revoked),
            ]);
        }

        return implode('. ', $sentences).'.';
    }

    /**
     * The live summary of the selection beside the checkboxes: how many permissions and which areas,
     * what saving would change compared with the role being edited, and the sensitive ones chosen.
     *
     * @return list<Text>
     */
    protected static function permissionSummarySchema(): array
    {
        return [
            Text::make(fn (Get $get): string => self::permissionSelectionOverview(self::selectedPermissions($get(self::PERMISSIONS_FIELD)))),
            self::permissionChangeSummaryText(),
            Text::make(TranslatedText::of('admin/roles.summary.sensitive_heading'))->weight(FontWeight::SemiBold),
            Text::make(fn (Get $get): string => self::sensitivePermissionsText(self::selectedPermissions($get(self::PERMISSIONS_FIELD)))),
        ];
    }

    /**
     * A required checkbox that appears only when the selection grants a sensitive permission the role
     * did not have (any sensitive permission for a new role); hidden, it is not validated.
     */
    protected static function sensitiveAcknowledgementField(): Checkbox
    {
        return Checkbox::make(self::ACKNOWLEDGE_SENSITIVE_FIELD)
            ->label(TranslatedText::of('admin/roles.form.acknowledge_sensitive'))
            ->accepted()
            ->validationMessages(['accepted' => TranslatedText::of('admin/roles.form.acknowledge_sensitive_required')])
            ->visible(fn (Get $get, ?Role $record): bool => self::grantsNewSensitivePermission(
                self::selectedPermissions(self::permissionsByArea($record)),
                self::selectedPermissions($get(self::PERMISSIONS_FIELD)),
            ))
            ->dehydrated(false);
    }

    /**
     * Whether the selection grants a sensitive permission that was not granted before.
     *
     * @param  list<AdminPermission>  $before
     * @param  list<AdminPermission>  $after
     */
    protected static function grantsNewSensitivePermission(array $before, array $after): bool
    {
        return array_any($after, fn (AdminPermission $adminPermission): bool => $adminPermission->isSensitive() && ! \in_array($adminPermission, $before, true));
    }

    /**
     * How many permissions are selected and which panel areas they cover.
     *
     * @param  list<AdminPermission>  $adminPermissions
     */
    protected static function permissionSelectionOverview(array $adminPermissions): string
    {
        $count = trans_choice('admin/roles.summary.count', \count($adminPermissions), ['count' => \count($adminPermissions)]);
        $areas = array_values(array_unique(array_map(
            static fn (AdminPermission $adminPermission): string => TranslatedText::of('admin/roles.catalog.areas.'.$adminPermission->area()),
            $adminPermissions,
        )));

        return $count.'. '.($areas === []
            ? TranslatedText::of('admin/roles.summary.no_areas')
            : TranslatedText::of('admin/roles.summary.areas', ['areas' => implode(', ', $areas)]));
    }

    /**
     * The sensitive permissions among the selected ones, by name, or a sentence saying there are none.
     *
     * @param  list<AdminPermission>  $adminPermissions
     */
    protected static function sensitivePermissionsText(array $adminPermissions): string
    {
        $sensitivePermissions = array_values(array_filter(
            $adminPermissions,
            static fn (AdminPermission $adminPermission): bool => $adminPermission->isSensitive(),
        ));

        return $sensitivePermissions === []
            ? TranslatedText::of('admin/roles.summary.no_sensitive')
            : self::permissionLabels($sensitivePermissions);
    }

    /**
     * The panel areas of the catalog, in catalog order.
     *
     * @return list<string>
     */
    protected static function permissionAreas(): array
    {
        return array_values(array_unique(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->area(),
            AdminPermission::cases(),
        )));
    }

    /**
     * The checkboxes of one area, with each permission's description under its name. A change replaces
     * the whole selection, since a requirement or a dependent may live in another area.
     */
    private static function areaCheckboxList(string $area): CheckboxList
    {
        $options = [];
        $descriptions = [];

        foreach (AdminPermission::cases() as $adminPermission) {
            if ($adminPermission->area() === $area) {
                $options[$adminPermission->value] = $adminPermission->label();
                $descriptions[$adminPermission->value] = self::permissionDescription($adminPermission);
            }
        }

        return CheckboxList::make(self::PERMISSIONS_FIELD.'.'.$area)
            ->label(TranslatedText::of('admin/roles.catalog.areas.'.$area))
            ->options($options)
            ->descriptions($descriptions)
            ->columns(2)
            ->bulkToggleable()
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set, mixed $old) use ($area): void {
                $permissionsByAreaAfter = $get(self::PERMISSIONS_FIELD);
                $permissionsByAreaBefore = \is_array($permissionsByAreaAfter) ? $permissionsByAreaAfter : [];
                $permissionsByAreaBefore[$area] = $old;

                $set(self::PERMISSIONS_FIELD, self::applyPermissionDependencies($permissionsByAreaBefore, $permissionsByAreaAfter));
            });
    }

    /**
     * A permission's description, followed on its own line by the sensitive mark when it has one.
     */
    private static function permissionDescription(AdminPermission $adminPermission): string|HtmlString
    {
        if (! $adminPermission->isSensitive()) {
            return $adminPermission->description();
        }

        return new HtmlString(e($adminPermission->description()).'<br><strong>'.e(TranslatedText::of('admin/roles.form.sensitive')).'</strong>');
    }

    /**
     * @param  list<AdminPermission>  $adminPermissions
     * @return array<string, list<string>>
     */
    private static function groupByArea(array $adminPermissions): array
    {
        $permissionsByArea = array_fill_keys(self::permissionAreas(), []);

        foreach ($adminPermissions as $adminPermission) {
            $permissionsByArea[$adminPermission->area()][] = $adminPermission->value;
        }

        return $permissionsByArea;
    }

    /**
     * @param  list<AdminPermission>  $adminPermissions
     */
    private static function permissionLabels(array $adminPermissions): string
    {
        return implode(', ', array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->label(),
            $adminPermissions,
        ));
    }
}

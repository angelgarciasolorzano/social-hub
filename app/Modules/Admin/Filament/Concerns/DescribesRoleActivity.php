<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Illuminate\Support\Str;

/**
 * Describes a role change in one translated sentence from its event, its stored properties and its
 * loaded subject, without running queries and without free text.
 */
trait DescribesRoleActivity
{
    /**
     * The sentence that says what changed; the names of duplicated source roles come in `$roleNamesById`.
     *
     * @param  array<int, string>  $roleNamesById
     */
    protected static function roleActivityDetail(ActivityLog $activityLog, array $roleNamesById = []): string
    {
        return match (ActivityLogAdminEvent::tryFrom($activityLog->event ?? '')) {
            ActivityLogAdminEvent::RolePermissionGranted => self::permissionDetail($activityLog, 'permission_granted'),
            ActivityLogAdminEvent::RolePermissionRevoked => self::permissionDetail($activityLog, 'permission_revoked'),
            ActivityLogAdminEvent::RoleGranted => self::userDetail($activityLog, 'role_granted'),
            ActivityLogAdminEvent::RoleRevoked => self::userDetail($activityLog, 'role_revoked'),
            ActivityLogAdminEvent::AdminBootstrapped => self::userDetail($activityLog, 'bootstrapped'),
            ActivityLogAdminEvent::RoleCreated => self::createdDetail($activityLog),
            ActivityLogAdminEvent::RoleUpdated => self::updatedDetail($activityLog),
            ActivityLogAdminEvent::RoleDuplicated => self::duplicatedDetail($activityLog, $roleNamesById),
            ActivityLogAdminEvent::RoleDeleted => self::roleHistoryText('deleted'),
            default => self::roleHistoryText('unknown'),
        };
    }

    /**
     * The permission that was added to or removed from the role, by its cataloged name.
     */
    private static function permissionDetail(ActivityLog $activityLog, string $detailKey): string
    {
        $permission = $activityLog->getProperty('permission');
        $adminPermission = \is_string($permission) ? AdminPermission::tryFrom($permission) : null;

        if (! $adminPermission instanceof AdminPermission) {
            return self::roleHistoryText('unknown');
        }

        return self::roleHistoryText($detailKey, ['permission' => $adminPermission->label()]);
    }

    /**
     * The user who received or lost the role: the activity's subject, or its id once deleted.
     */
    private static function userDetail(ActivityLog $activityLog, string $detailKey): string
    {
        if ($activityLog->subject instanceof User) {
            return self::roleHistoryText($detailKey, ['user' => $activityLog->subject->name]);
        }

        if ($activityLog->subject_type !== User::MORPH_NAME || $activityLog->subject_id === null) {
            return self::roleHistoryText('unknown');
        }

        return self::roleHistoryText($detailKey, ['user' => TranslatedText::of('admin/activity-log.parties.deleted_causer', [
            'type' => Str::lower(TranslatedText::of('admin/activity-log.parties.types.user')),
            'id' => $activityLog->subject_id,
        ])]);
    }

    /**
     * How many permissions the role was created with.
     */
    private static function createdDetail(ActivityLog $activityLog): string
    {
        $permissions = $activityLog->getProperty('permissions');

        if (! \is_array($permissions)) {
            return self::roleHistoryText('unknown');
        }

        return trans_choice('admin/roles.relations.history.detail.created', \count($permissions), ['count' => \count($permissions)]);
    }

    /**
     * Which of the role's texts were changed: its name, its description or both.
     */
    private static function updatedDetail(ActivityLog $activityLog): string
    {
        $changedAttributes = $activityLog->getProperty('changed_attributes');
        $changedAttributes = \is_array($changedAttributes) ? $changedAttributes : [];

        $changedLabel = \in_array('label', $changedAttributes, true);
        $changedDescription = \in_array('description', $changedAttributes, true);

        return self::roleHistoryText(match (true) {
            $changedLabel && $changedDescription => 'updated_label_and_description',
            $changedLabel => 'updated_label',
            $changedDescription => 'updated_description',
            default => 'updated',
        });
    }

    /**
     * The role this one was copied from, by its name or, once deleted, by its id.
     *
     * @param  array<int, string>  $roleNamesById
     */
    private static function duplicatedDetail(ActivityLog $activityLog, array $roleNamesById): string
    {
        $sourceRoleId = $activityLog->getProperty('source_role_id');

        if (! \is_int($sourceRoleId)) {
            return self::roleHistoryText('unknown');
        }

        return self::roleHistoryText('duplicated', [
            'role' => $roleNamesById[$sourceRoleId] ?? TranslatedText::of('admin/activity-log.parties.deleted_subject', [
                'type' => TranslatedText::of('admin/activity-log.parties.types.role'),
                'id' => $sourceRoleId,
            ]),
        ]);
    }

    /**
     * A text of the role history's details.
     *
     * @param  array<string, string|int>  $replace
     */
    private static function roleHistoryText(string $detailKey, array $replace = []): string
    {
        return TranslatedText::of('admin/roles.relations.history.detail.'.$detailKey, $replace);
    }
}

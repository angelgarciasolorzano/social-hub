<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Support\Translation\TranslatedText;

/**
 * The kinds of change a role's history groups its activity into: its permissions, the users who hold
 * it, and its own lifecycle.
 */
enum RoleActivityChangeType: string
{
    case Permissions = 'permissions';

    case Users = 'users';

    case Lifecycle = 'lifecycle';

    /**
     * The recorded events of this kind of change.
     *
     * @return list<ActivityLogAdminEvent>
     */
    public function events(): array
    {
        return match ($this) {
            self::Permissions => [
                ActivityLogAdminEvent::RolePermissionGranted,
                ActivityLogAdminEvent::RolePermissionRevoked,
            ],
            self::Users => [
                ActivityLogAdminEvent::RoleGranted,
                ActivityLogAdminEvent::RoleRevoked,
                ActivityLogAdminEvent::AdminBootstrapped,
            ],
            self::Lifecycle => [
                ActivityLogAdminEvent::RoleCreated,
                ActivityLogAdminEvent::RoleUpdated,
                ActivityLogAdminEvent::RoleDuplicated,
                ActivityLogAdminEvent::RoleDeleted,
            ],
        };
    }

    public function label(): string
    {
        return TranslatedText::of('admin/roles.relations.history.change_types.'.$this->value);
    }

    /**
     * The kind of change of a stored event, or null for an event that is not a role change.
     */
    public static function forEvent(?string $event): ?self
    {
        foreach (self::cases() as $roleActivityChangeType) {
            foreach ($roleActivityChangeType->events() as $activityLogAdminEvent) {
                if ($activityLogAdminEvent->value === $event) {
                    return $roleActivityChangeType;
                }
            }
        }

        return null;
    }
}

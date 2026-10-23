<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Enums\Events;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;

enum ActivityLogAdminEvent: string implements ActivityLogEvent
{
    case RoleGranted = 'admin.role_granted';

    case RoleRevoked = 'admin.role_revoked';

    case RolePermissionGranted = 'admin.role_permission_granted';

    case RolePermissionRevoked = 'admin.role_permission_revoked';

    case AdminBootstrapped = 'admin.bootstrapped';

    case InvitationIssued = 'admin.invitation_issued';

    case InvitationAccepted = 'admin.invitation_accepted';

    case BackupCreated = 'admin.backup_created';

    case BackupDownloaded = 'admin.backup_downloaded';

    public function eventName(): string
    {
        return $this->value;
    }

    public function logName(): ActivityLogName
    {
        return ActivityLogName::Admin;
    }
}

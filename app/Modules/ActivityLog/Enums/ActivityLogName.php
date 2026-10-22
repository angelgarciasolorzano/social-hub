<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Enums;

use Illuminate\Support\Facades\Config;

enum ActivityLogName: string
{
    case Auth = 'auth';

    case Content = 'content';

    case Likes = 'likes';

    case TrustedDevices = 'trusted_devices';

    case Account = 'account';

    case Admin = 'admin';

    /**
     * Days the records of this log are kept before `activity-log:purge` deletes them,
     * read from `module.activity_log.retention_days.<log>`.
     */
    public function retentionDays(): int
    {
        return Config::integer("module.activity_log.retention_days.{$this->value}");
    }
}

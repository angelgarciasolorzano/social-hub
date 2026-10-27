<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Enums;

enum AnnouncementType: string
{
    case Info = 'info';

    case Warning = 'warning';

    case Critical = 'critical';

    /**
     * Readable name shown in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    /**
     * Weight used to show the most severe announcements first; a higher value is more severe.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Warning => 2,
            self::Critical => 3,
        };
    }
}

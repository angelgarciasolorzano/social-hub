<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Enums;

use App\Support\Translation\TranslatedText;

enum AnnouncementType: string
{
    case Info = 'info';

    case Warning = 'warning';

    case Critical = 'critical';

    /**
     * Readable name shown in the admin panel, in the panel's current language.
     */
    public function label(): string
    {
        return TranslatedText::of('admin/announcements.options.type.'.$this->value);
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

<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Enums;

use App\Support\Translation\TranslatedText;

enum AnnouncementAudience: string
{
    /**
     * Every visitor, signed in or not.
     */
    case All = 'all';

    /**
     * Only signed-in users.
     */
    case Authenticated = 'authenticated';

    /**
     * Only signed-in users without confirmed two-factor authentication.
     */
    case WithoutTwoFactor = 'without_two_factor';

    /**
     * Readable name shown in the admin panel, in the panel's current language.
     */
    public function label(): string
    {
        return TranslatedText::of('admin/announcements.options.audience.'.$this->value);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Enums;

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
     * Readable name shown in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::All => 'Everyone',
            self::Authenticated => 'Signed-in users',
            self::WithoutTwoFactor => 'Users without two-factor authentication',
        };
    }
}

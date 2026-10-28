<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Enums;

enum AnnouncementDisplay: string
{
    /**
     * A floating card that informs without blocking the page.
     */
    case Toast = 'toast';

    /**
     * A modal window that interrupts the user until they close it.
     */
    case Dialog = 'dialog';

    /**
     * Readable name shown in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Toast => 'Toast',
            self::Dialog => 'Dialog',
        };
    }

    /**
     * When to choose this presentation, shown under each option of the admin form.
     */
    public function description(): string
    {
        return match ($this) {
            self::Toast => 'A floating notice that does not interrupt. Best for news and routine notices.',
            self::Dialog => 'A window the user must close before going on. Keep it for urgent notices.',
        };
    }
}

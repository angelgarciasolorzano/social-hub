<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Enums;

use App\Support\Translation\TranslatedText;

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
     * Readable name shown in the admin panel, in the panel's current language.
     */
    public function label(): string
    {
        return TranslatedText::of('admin/announcements.options.display.'.$this->value);
    }

    /**
     * When to choose this presentation, shown under each option of the admin form.
     */
    public function description(): string
    {
        return TranslatedText::of('admin/announcements.options.display.'.$this->value.'_description');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use Filament\Notifications\Notification;

/**
 * Tells the administrator why `AdminAuthorizationService` refused a change, with the failure's message.
 */
trait NotifiesAdminAuthorizationFailure
{
    protected static function notifyAdminAuthorizationFailure(AdminAuthorizationException $adminAuthorizationException): void
    {
        Notification::make()
            ->title($adminAuthorizationException->failure->message())
            ->danger()
            ->send();
    }
}

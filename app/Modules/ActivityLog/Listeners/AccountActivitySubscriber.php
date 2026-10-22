<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Listeners;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAccountEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\User\Events\AccountDeleted;
use App\User\Events\AccountImageUpdated;
use App\User\Events\AccountUpdated;

/**
 * Records changes to a user's own account in the `account` log. Updates keep
 * only the names of the changed attributes, never their values.
 */
final readonly class AccountActivitySubscriber
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            AccountUpdated::class => 'handleAccountUpdated',
            AccountImageUpdated::class => 'handleAccountImageUpdated',
            AccountDeleted::class => 'handleAccountDeleted',
        ];
    }

    public function handleAccountUpdated(AccountUpdated $accountUpdated): void
    {
        $this->activityLogger->record(
            ActivityLogAccountEvent::Updated,
            $accountUpdated->user,
            $accountUpdated->user,
            ['changed_attributes' => $accountUpdated->changedAttributes],
        );
    }

    public function handleAccountImageUpdated(AccountImageUpdated $accountImageUpdated): void
    {
        $this->activityLogger->record(
            ActivityLogAccountEvent::ImageUpdated,
            $accountImageUpdated->user,
            $accountImageUpdated->user,
            ['image_type' => $accountImageUpdated->userImageType->value],
        );
    }

    /**
     * The deleted user stays as subject and causer by id, so the record survives until it is purged.
     */
    public function handleAccountDeleted(AccountDeleted $accountDeleted): void
    {
        $this->activityLogger->record(
            ActivityLogAccountEvent::Deleted,
            $accountDeleted->user,
            $accountDeleted->user,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\User\Modules\AccountStatus\Services;

use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Suspends and reactivates accounts. Authorization belongs to UserPolicy and is
 * applied by the caller; this service only enforces the state transition.
 */
final readonly class AccountStatusService
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private TrustedDeviceService $trustedDeviceService,
    ) {}

    /**
     * Suspend an active account and revoke its trusted devices.
     *
     * @throws InvalidArgumentException when the reason is blank or too long
     * @throws LogicException when the account is already suspended
     */
    public function suspend(User $user, User $administrator, string $reason): AccountStatusChange
    {
        return $this->changeStatus($user, $administrator, $reason, AccountStatus::Suspended);
    }

    /**
     * Reactivate a suspended account; its revoked trusted devices stay revoked.
     *
     * @throws InvalidArgumentException when the reason is blank or too long
     * @throws LogicException when the account is already active
     */
    public function reactivate(User $user, User $administrator, string $reason): AccountStatusChange
    {
        return $this->changeStatus($user, $administrator, $reason, AccountStatus::Active);
    }

    /**
     * Lock the account, record the change with its reason and audit it, all in one
     * transaction, so a concurrent change or a failure leaves nothing behind.
     */
    private function changeStatus(User $user, User $administrator, string $reason, AccountStatus $accountStatus): AccountStatusChange
    {
        $normalizedReason = $this->normalizedReason($reason);

        $accountStatusChange = DB::transaction(function () use ($user, $administrator, $normalizedReason, $accountStatus): AccountStatusChange {
            $lockedUser = User::query()->lockForUpdate()->whereKey($user->id)->firstOrFail();

            throw_if($lockedUser->status === $accountStatus, LogicException::class, "The account is already {$accountStatus->value}.");

            $accountStatusChange = AccountStatusChange::query()->create([
                'user_id' => $lockedUser->id,
                'status' => $accountStatus,
                'reason' => $normalizedReason,
                'changed_by' => $administrator->id,
            ]);

            $lockedUser->forceFill(['status' => $accountStatus])->save();

            if ($accountStatus === AccountStatus::Suspended) {
                $this->trustedDeviceService->revokeAll($lockedUser, request());
            }

            $this->activityLogger->record(
                $accountStatus === AccountStatus::Suspended
                    ? ActivityLogAdminEvent::AccountSuspended
                    : ActivityLogAdminEvent::AccountReactivated,
                $accountStatusChange,
                $administrator,
                ['target_user_id' => $lockedUser->id],
            );

            return $accountStatusChange;
        });

        $user->refresh();

        return $accountStatusChange;
    }

    /**
     * Trim the reason and reject it when it is empty or longer than the column allows.
     *
     * @throws InvalidArgumentException
     */
    private function normalizedReason(string $reason): string
    {
        $normalizedReason = trim($reason);

        throw_if($normalizedReason === '', InvalidArgumentException::class, 'A reason is required to change the account status.');
        throw_if(mb_strlen($normalizedReason) > AccountStatusChange::REASON_MAX_LENGTH, InvalidArgumentException::class, 'The reason is too long.');

        return $normalizedReason;
    }
}

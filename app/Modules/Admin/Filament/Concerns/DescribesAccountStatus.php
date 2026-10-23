<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\User\Enums\AccountStatus;

/**
 * Shared badge label, color and filter options for an account status, so the users
 * table, the user detail and its status history show it the same way.
 */
trait DescribesAccountStatus
{
    protected static function accountStatusLabel(AccountStatus $accountStatus): string
    {
        return $accountStatus->label();
    }

    protected static function accountStatusColor(AccountStatus $accountStatus): string
    {
        return match ($accountStatus) {
            AccountStatus::Active => 'success',
            AccountStatus::Suspended => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    protected static function accountStatusOptions(): array
    {
        $accountStatusOptions = [];

        foreach (AccountStatus::cases() as $accountStatus) {
            $accountStatusOptions[$accountStatus->value] = $accountStatus->label();
        }

        return $accountStatusOptions;
    }
}

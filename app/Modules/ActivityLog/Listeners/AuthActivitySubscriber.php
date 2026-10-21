<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Listeners;

use App\Auth\Modules\Password\Events\PasswordUpdated;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\User\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

/**
 * Records sign-in, password and two-factor activity in the `auth` log.
 * Credentials, codes and secrets carried by the events are never stored.
 */
final readonly class AuthActivitySubscriber
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            PasswordUpdated::class => 'handlePasswordUpdated',
            PasswordReset::class => 'handlePasswordReset',
            TwoFactorAuthenticationConfirmed::class => 'handleTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'handleTwoFactorDisabled',
            RecoveryCodesGenerated::class => 'handleRecoveryCodesGenerated',
        ];
    }

    public function handleLogin(Login $login): void
    {
        if (! $login->user instanceof User) {
            return;
        }

        $this->recordForUser(ActivityLogAuthEvent::UserLoggedIn, $login->user, ['guard' => $login->guard]);
    }

    public function handleLogout(Logout $logout): void
    {
        if (! $logout->user instanceof User) {
            return;
        }

        $this->recordForUser(ActivityLogAuthEvent::UserLoggedOut, $logout->user, ['guard' => $logout->guard]);
    }

    /**
     * The attempted account is the subject when it exists; there is no causer
     * because nobody is authenticated, and the attempted email is not kept.
     */
    public function handleFailed(Failed $failed): void
    {
        $this->activityLogger->record(
            ActivityLogAuthEvent::LoginFailed,
            subject: $failed->user instanceof User ? $failed->user : null,
            properties: ['guard' => $failed->guard],
        );
    }

    public function handlePasswordUpdated(PasswordUpdated $passwordUpdated): void
    {
        $this->recordForUser(ActivityLogAuthEvent::PasswordUpdated, $passwordUpdated->user);
    }

    public function handlePasswordReset(PasswordReset $passwordReset): void
    {
        if (! $passwordReset->user instanceof User) {
            return;
        }

        $this->recordForUser(ActivityLogAuthEvent::PasswordReset, $passwordReset->user);
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $twoFactorAuthenticationConfirmed): void
    {
        $this->recordForUser(ActivityLogAuthEvent::TwoFactorConfirmed, $twoFactorAuthenticationConfirmed->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $twoFactorAuthenticationDisabled): void
    {
        $this->recordForUser(ActivityLogAuthEvent::TwoFactorDisabled, $twoFactorAuthenticationDisabled->user);
    }

    public function handleRecoveryCodesGenerated(RecoveryCodesGenerated $recoveryCodesGenerated): void
    {
        $this->recordForUser(ActivityLogAuthEvent::RecoveryCodesRegenerated, $recoveryCodesGenerated->user);
    }

    /**
     * Record an action the user performed on their own account.
     *
     * @param  array<string, scalar|null>  $properties
     */
    private function recordForUser(ActivityLogAuthEvent $activityLogAuthEvent, User $user, array $properties = []): void
    {
        $this->activityLogger->record($activityLogAuthEvent, $user, $user, $properties);
    }
}

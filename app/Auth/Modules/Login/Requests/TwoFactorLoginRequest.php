<?php

declare(strict_types=1);

namespace App\Auth\Modules\Login\Requests;

use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;
use Override;

/**
 * Fortify's two-factor challenge request, bound in its place so an account
 * suspended after the password step cannot finish signing in.
 */
class TwoFactorLoginRequest extends FortifyTwoFactorLoginRequest
{
    /**
     * Whether a user is waiting for the challenge; a suspended one is sent back to the login.
     */
    #[Override]
    public function hasChallengedUser(): bool
    {
        if (! parent::hasChallengedUser()) {
            return false;
        }

        $this->challengedUser();

        return true;
    }

    /**
     * The user attempting the challenge, rejected before any code is checked or consumed.
     */
    #[Override]
    public function challengedUser(): mixed
    {
        $challengedUser = parent::challengedUser();

        if ($challengedUser instanceof User && $challengedUser->isSuspended()) {
            $this->session()->forget(['login.id', 'login.remember']);

            throw new HttpResponseException(
                to_route('login')->withErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]),
            );
        }

        return $challengedUser;
    }
}

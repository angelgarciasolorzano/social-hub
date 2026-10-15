<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Concerns;

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceOperationResult;
use App\Auth\Modules\TrustedDevice\Services\TwoFactorCodeVerifier;
use App\User\Models\User;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;

/**
 * Shared by the trusted-device requests that must re-authenticate the user
 * with a current TOTP code before creating or extending trust.
 *
 * Pair with a FormRequest that merges otpRules() into its own rules().
 */
trait RequiresTwoFactorOtp
{
    /**
     * The OTP rule, present only once two-factor is confirmed; otherwise the
     * request fails with the `two_factor` error alone.
     *
     * @return array<string, FluentRuleContract>
     */
    protected function otpRules(): array
    {
        if (! $this->hasConfirmedTwoFactor()) {
            return [];
        }

        return [
            'otp_code' => FluentRule::string()
                ->required(message: 'Debes ingresar el código de verificación.')
                ->min(6, message: 'El código debe tener 6 dígitos.')
                ->max(6, message: 'El código debe tener 6 dígitos.')
                ->rule('regex:/^[0-9]+$/', 'El código solo puede contener números.'),
        ];
    }

    /**
     * Reject the request before any side effect when two-factor is not
     * confirmed or the TOTP code is not valid.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasConfirmedTwoFactor()) {
                $validator->errors()->add('two_factor', TrustedDeviceOperationResult::TwoFactorRequired->message());

                return;
            }

            if ($validator->errors()->has('otp_code')) {
                return;
            }

            $user = $this->user();

            if ($user instanceof User
                && ! resolve(TwoFactorCodeVerifier::class)->verifyTotp($user, $this->string('otp_code')->toString())) {
                $validator->errors()->add('otp_code', 'El código de verificación es incorrecto.');
            }
        });
    }

    private function hasConfirmedTwoFactor(): bool
    {
        $user = $this->user();

        return $user instanceof User && resolve(TwoFactorCodeVerifier::class)->hasConfirmedTwoFactor($user);
    }
}

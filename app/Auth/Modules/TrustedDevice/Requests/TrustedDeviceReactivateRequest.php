<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use App\Auth\Models\TrustedDevice;
use App\User\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use Throwable;

class TrustedDeviceReactivateRequest extends FormRequest
{
    use HasFluentRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'otp_code' => FluentRule::string()
                ->nullable()
                ->requiredIf(fn (): bool => ! $this->cookieMatchesDevice())
                ->min(6, message: 'El código debe tener al menos 6 caracteres.')
                ->max(10, message: 'El código es demasiado largo.'),
        ];
    }

    /**
     * Verifies the OTP only when the browser doesn't already hold the device
     * cookie; otherwise reactivation is silent.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->cookieMatchesDevice()) {
                return;
            }

            $code = $this->input('otp_code');

            if (! is_string($code) || $code === '') {
                return;
            }

            if (! $this->isValidOtpCode($code)) {
                $validator->errors()->add('otp_code', 'El código de verificación es incorrecto.');
            }
        });
    }

    /** True when `hash(cookie) === device.token_hash`. */
    public function cookieMatchesDevice(): bool
    {
        $rawToken = $this->cookie('trusted_device');

        if (! is_string($rawToken) || $rawToken === '') {
            return false;
        }

        $device = $this->route('trustedDevice');

        if (! $device instanceof TrustedDevice) {
            return false;
        }

        return hash('sha256', $rawToken) === $device->token_hash;
    }

    /**
     * Mirrors Fortify's TOTP/recovery check without depending on the
     * `login.id` session key.
     */
    private function isValidOtpCode(string $code): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $encryptedSecret = $user->two_factor_secret;

        if (is_string($encryptedSecret) && $encryptedSecret !== '') {
            try {
                $secret = Fortify::currentEncrypter()->decrypt($encryptedSecret);

                if (is_string($secret) && resolve(TwoFactorAuthenticationProvider::class)->verify($secret, $code)) {
                    return true;
                }
            } catch (Throwable $throwable) {
                Log::warning('TrustedDevice reactivate: TOTP secret decrypt failed.', [
                    'user_id' => $user->getKey(),
                    'exception' => $throwable::class,
                ]);
            }
        }

        $encryptedRecoveryCodes = $user->two_factor_recovery_codes;

        if (is_string($encryptedRecoveryCodes) && $encryptedRecoveryCodes !== '') {
            try {
                $decoded = Fortify::currentEncrypter()->decrypt($encryptedRecoveryCodes);

                if (is_string($decoded)) {
                    $recoveryCodes = json_decode($decoded, true);

                    if (is_array($recoveryCodes) && in_array($code, $recoveryCodes, true)) {
                        return true;
                    }
                }
            } catch (Throwable $throwable) {
                Log::warning('TrustedDevice reactivate: recovery codes decrypt failed.', [
                    'user_id' => $user->getKey(),
                    'exception' => $throwable::class,
                ]);
            }
        }

        return false;
    }
}

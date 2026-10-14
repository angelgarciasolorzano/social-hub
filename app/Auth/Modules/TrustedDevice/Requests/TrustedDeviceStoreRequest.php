<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceOperationResult;
use App\Auth\Modules\TrustedDevice\Services\TwoFactorCodeVerifier;
use App\User\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class TrustedDeviceStoreRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request. The OTP is only
     * validated once two-factor is confirmed; otherwise the request fails with
     * the `two_factor` error alone.
     *
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        $rules = [
            'name' => FluentRule::string()
                ->nullable()
                ->max(50, message: 'El nombre no puede tener más de 50 caracteres.'),
        ];

        if ($this->hasConfirmedTwoFactor()) {
            $rules['otp_code'] = FluentRule::string()
                ->required(message: 'Debes ingresar el código de verificación.')
                ->min(6, message: 'El código debe tener 6 dígitos.')
                ->max(6, message: 'El código debe tener 6 dígitos.')
                ->rule('regex:/^[0-9]+$/', 'El código solo puede contener números.');
        }

        return $rules;
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

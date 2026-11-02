<?php

declare(strict_types=1);

namespace App\User\Modules\TwoFactor\Requests;

use App\Auth\Modules\TrustedDevice\Services\TwoFactorCodeVerifier;
use App\Support\Translation\TranslatedText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class TwoFactorDisableRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasEnabledTwoFactorAuthentication();
    }

    /** @return array<string, FluentRuleContract> */
    public function rules(): array
    {
        return [
            'password' => FluentRule::string()
                ->required(message: TranslatedText::of('setting/two-factor.validation.password_required'))
                ->currentPassword(message: TranslatedText::of('setting/two-factor.validation.password_mismatch')),

            'code' => FluentRule::string()
                ->required(message: TranslatedText::of('setting/two-factor.validation.code_required'))
                ->max(21, message: TranslatedText::of('setting/two-factor.validation.code_too_long'))
                ->rule(
                    'regex:/^([0-9]{6}|[A-Za-z0-9]{10}-[A-Za-z0-9]{10})$/',
                    TranslatedText::of('setting/two-factor.validation.code_format'),
                ),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && ! $this->hasValidCode()) {
                $validator->errors()->add('code', TranslatedText::of('setting/two-factor.validation.code_invalid'));
            }
        });
    }

    /**
     * Determine if the request has a valid two factor code: a TOTP code or a
     * single-use recovery code. Disabling two-factor wipes the recovery codes,
     * so consuming the one used leaves nothing inconsistent.
     */
    protected function hasValidCode(): bool
    {
        $user = $this->user();

        if ($user === null || $user->two_factor_secret === null) {
            return false;
        }

        return DB::transaction(
            fn (): bool => resolve(TwoFactorCodeVerifier::class)->verifyCode($user, $this->string('code')->toString())
        );
    }
}

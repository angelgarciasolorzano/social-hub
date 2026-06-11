<?php

namespace App\User\Requests\TwoFactorAuthentication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class DisableTwoFactorRequest extends FormRequest
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
                ->required(message: 'Debes confirmar tu contraseña')
                ->currentPassword(message: 'La contraseña proporcionada no coincide con tu contraseña actual.'),

            'code' => FluentRule::string()
                ->required(message: 'Debes ingresar el código de autenticación.')
                ->min(6, message: 'El código debe tener 6 dígitos.')
                ->max(6, message: 'El código debe tener 6 dígitos.')
                ->rule('regex:/^[0-9]+$/', 'El código solo puede contener números.'),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isEmpty() && ! $this->hasValidCode()) {
                $validator->errors()->add('code', 'El código de autenticación proporcionado no es válido.');
            }
        });
    }

    /**
     * Determine if the request has a valid two factor code.
     */
    protected function hasValidCode(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->two_factor_secret) {
            return false;
        }

        /** @var string $decryptedSecret */
        $decryptedSecret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        /** @var string $code */
        $code = $this->input('code');

        return resolve(TwoFactorAuthenticationProvider::class)->verify(
            $decryptedSecret,
            $code
        );
    }
}

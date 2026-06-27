<?php

declare(strict_types=1);

namespace App\User\Requests\TwoFactorAuthentication;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class RegenerateRecoveryCodesRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, FluentRuleContract> */
    public function rules(): array
    {
        return [
            'password' => FluentRule::string()
                ->required(message: 'Debes confirmar tu contraseña.')
                ->currentPassword(message: 'La contraseña proporcionada no es correcta.'),
        ];
    }
}

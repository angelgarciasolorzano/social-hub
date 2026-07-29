<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class TrustedDeviceDestroyRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'password' => FluentRule::string()
                ->required(message: 'Debes confirmar tu contraseña.')
                ->currentPassword(message: 'La contraseña proporcionada no es correcta.'),
            'terms' => FluentRule::boolean()
                ->required(message: 'Debes confirmar que entiendes las consecuencias.')
                ->accepted(message: 'Debes aceptar los términos para continuar.'),
        ];
    }
}

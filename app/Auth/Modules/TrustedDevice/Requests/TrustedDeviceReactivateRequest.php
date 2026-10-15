<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class TrustedDeviceReactivateRequest extends FormRequest
{
    use HasFluentRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Only the format is checked here; TrustedDeviceService verifies the code
     * (TOTP or single-use recovery code) inside its transaction.
     *
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'otp_code' => FluentRule::string()
                ->required(message: 'Debes ingresar el código de verificación.')
                ->max(21, message: 'El código es demasiado largo.')
                ->rule(
                    'regex:/^([0-9]{6}|[A-Za-z0-9]{10}-[A-Za-z0-9]{10})$/',
                    'Ingresa un código de 6 dígitos o un código de recuperación válido.',
                ),
        ];
    }
}

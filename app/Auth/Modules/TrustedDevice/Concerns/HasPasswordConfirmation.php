<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Concerns;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

/**
 * Shared rules for trusted-device endpoints that require the user to confirm
 * their password and acknowledge the consequences before destructive actions.
 *
 * Pair with a FormRequest; the host class still owns authorize() routing
 * semantics if it needs anything beyond "logged in".
 */
trait HasPasswordConfirmation
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
            'password' => FluentRule::string()
                ->required(message: 'Debes confirmar tu contraseña.')
                ->currentPassword(message: 'La contraseña proporcionada no es correcta.'),
            'terms' => FluentRule::boolean()
                ->required(message: 'Debes confirmar que entiendes las consecuencias.')
                ->accepted(message: 'Debes aceptar los términos para continuar.'),
        ];
    }
}

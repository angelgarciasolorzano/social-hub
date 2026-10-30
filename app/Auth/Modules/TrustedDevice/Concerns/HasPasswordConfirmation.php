<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Concerns;

use App\Support\Translation\TranslatedText;
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
                ->required(message: TranslatedText::of('setting/trusted-devices.validation.password_required'))
                ->currentPassword(message: TranslatedText::of('setting/trusted-devices.validation.password_incorrect')),
            'terms' => FluentRule::boolean()
                ->required(message: TranslatedText::of('setting/trusted-devices.validation.terms_required'))
                ->accepted(message: TranslatedText::of('setting/trusted-devices.validation.terms_accepted')),
        ];
    }
}

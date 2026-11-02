<?php

declare(strict_types=1);

namespace App\User\Modules\TwoFactor\Requests;

use App\Support\Translation\TranslatedText;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class TwoFactorRegenerateRecoveryCodesRequest extends FormRequest
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
                ->required(message: TranslatedText::of('setting/two-factor.validation.password_required'))
                ->currentPassword(message: TranslatedText::of('setting/two-factor.validation.password_incorrect')),
        ];
    }
}

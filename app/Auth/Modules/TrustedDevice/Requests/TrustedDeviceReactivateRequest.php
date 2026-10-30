<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use App\Support\Translation\TranslatedText;
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
                ->required(message: TranslatedText::of('setting/trusted-devices.validation.code_required'))
                ->max(21, message: TranslatedText::of('setting/trusted-devices.validation.code_too_long'))
                ->rule(
                    'regex:/^([0-9]{6}|[A-Za-z0-9]{10}-[A-Za-z0-9]{10})$/',
                    TranslatedText::of('setting/trusted-devices.validation.code_format'),
                ),
        ];
    }
}

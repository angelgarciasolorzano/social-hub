<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use App\Support\Translation\TranslatedText;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class TrustedDeviceUpdateRequest extends FormRequest
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
            'name' => FluentRule::string()
                ->required(message: TranslatedText::of('setting/trusted-devices.validation.name_required'))
                ->max(50, message: TranslatedText::of('setting/trusted-devices.validation.name_max')),
        ];
    }
}

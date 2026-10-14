<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use App\Auth\Modules\TrustedDevice\Concerns\RequiresTwoFactorOtp;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\HasFluentRules;

class TrustedDeviceRenewRequest extends FormRequest
{
    use HasFluentRules;
    use RequiresTwoFactorOtp;

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
        return $this->otpRules();
    }
}

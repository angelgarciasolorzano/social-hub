<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class UpdateTrustedDeviceRequest extends FormRequest
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
                ->required(message: 'El nombre es obligatorio.')
                ->max(50, message: 'El nombre no puede tener más de 50 caracteres.'),
        ];
    }
}

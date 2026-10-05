<?php

declare(strict_types=1);

namespace App\User\Modules\AccountSettings\Requests;

use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class AccountSettingsUpdateRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
            'phone' => FluentRule::string()
                ->nullable()
                ->max(32)
                ->regex('/^\+?(?=(?:\D*\d){7,15}\D*$)[0-9 ().-]+$/'),
            'preferredLocale' => FluentRule::string()->required()->enum(PreferredLocale::class),
            'biography' => FluentRule::string()->nullable()->max(160),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Requests;

use App\Support\Translation\TranslatedText;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class FriendshipSearchRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * The fewest characters a search needs, so a single letter does not list half of the users.
     */
    public const int TERM_MIN_LENGTH = 2;

    /**
     * The longest search accepted, as long as a reasonable name.
     */
    public const int TERM_MAX_LENGTH = 50;

    /**
     * Any signed-in user may search; the route's middleware already requires it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'q' => FluentRule::string()
                ->required(message: TranslatedText::of('friendship/actions.validation.search_required'))
                ->min(self::TERM_MIN_LENGTH, message: TranslatedText::of('friendship/actions.validation.search_min'))
                ->max(self::TERM_MAX_LENGTH, message: TranslatedText::of('friendship/actions.validation.search_max')),
        ];
    }
}

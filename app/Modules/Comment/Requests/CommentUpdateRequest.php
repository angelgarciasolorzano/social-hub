<?php

declare(strict_types=1);

namespace App\Modules\Comment\Requests;

use App\Modules\Comment\Concerns\NormalizesCommentContent;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class CommentUpdateRequest extends FormRequest
{
    use HasFluentRules;
    use NormalizesCommentContent;

    /**
     * Ownership is enforced by the CommentPolicy before this request validates.
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
            'content' => FluentRule::string()
                ->required(message: 'El comentario es obligatorio')
                ->max(1000),
        ];
    }
}

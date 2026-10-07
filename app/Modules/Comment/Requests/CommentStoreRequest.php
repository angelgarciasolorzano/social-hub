<?php

declare(strict_types=1);

namespace App\Modules\Comment\Requests;

use App\Modules\Comment\Enums\CommentType;
use Illuminate\Foundation\Http\FormRequest;
use Override;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class CommentStoreRequest extends FormRequest
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
     * Strip control characters (keeping tabs and line breaks) and trim the content.
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $content = $this->input('content');

        if (is_string($content)) {
            $this->merge([
                'content' => trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content)),
            ]);
        }
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
            'commentable_type' => FluentRule::string()->required()->enum(CommentType::class),
            'commentable_id' => FluentRule::integer()->required(),
        ];
    }
}

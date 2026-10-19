<?php

declare(strict_types=1);

namespace App\Modules\Comment\Concerns;

/**
 * Shared by the comment requests that accept free text in `content`.
 *
 * Pair with a FormRequest: it runs before validation and leaves non-string input untouched.
 */
trait NormalizesCommentContent
{
    /**
     * Strip control characters (keeping tabs and line breaks) and trim the content.
     */
    protected function prepareForValidation(): void
    {
        $content = $this->input('content');

        if (is_string($content)) {
            $this->merge([
                'content' => trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content)),
            ]);
        }
    }
}

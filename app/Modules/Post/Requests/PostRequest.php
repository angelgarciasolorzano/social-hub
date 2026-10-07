<?php

declare(strict_types=1);

namespace App\Modules\Post\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

/**
 * Summary of PostRequest
 *
 * @property-read string $content
 * @property-read UploadedFile|null $image_file
 */
class PostRequest extends FormRequest
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
            'content' => FluentRule::string()
                ->required(message: 'El contenido de la publicación es obligatorio')
                ->min(10, message: 'El contenido de la publicación no debe ser menor a 10 caracteres'),
            'image_file' => FluentRule::file()
                ->nullable()
                ->mimes('png', 'jpg', 'webp')->message('El archivo debe ser de tipo png, jpg o webp')
                ->max(5120, message: 'El archivo no debe ser mayor a 5MB'),
        ];
    }
}

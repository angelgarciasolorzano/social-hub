<?php

namespace App\Post\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Summary of PostRequest
 * 
 * @property-read string $content
 * @property-read \Illuminate\Http\UploadedFile|null $image
 */
class PostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:10'],
            'image' => ['nullable', 'mimes:png,jpg, webp', 'max:5120'], 
        ];
    }

    /**
     * Get the custom messages for the validator errors.
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'El contenido de la publicación es obligatorio',
            'content.min' => 'El contenido de la publicación no debe ser menor a 10 caracteres',
            'image.mimes' => 'El archivo debe ser de tipo png, jpg o webp',
            'image.max' => 'El archivo no debe ser mayor a 5MB',
        ];
    }
}
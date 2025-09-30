<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'min:10', 'max:1000'],
            'commentable_type' => ['required', 'in:post,comment'],
            'commentable_id' => ['required', 'integer']
        ];
    }

    /**
     * Get the custom message for the validator errors.
     * Obtener los mensajes personalizados para los errores de validacion
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return  [
            'content.required' => 'El mensaje de comentario es obligatorio',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Determinar si el usuario está autorizado para hacer esta solicitud
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
            'content' => ['required', 'string', 'max:500', 'min:10'],
            'image_file' => ['nullable', 'mimes:jpeg,png,jpg,svg,webp', 'max:10240'],
        ];
    }

    /**
     * Get the custom messages for the validator errors.
     * Obtener los mensajes personalizados para los errores de validación
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'El contenido de la publicación es obligatorio',
            'content.min' => 'El contenido de la publicación no debe ser menor a 10 caracteres',
            'content.max' => 'El contenido de la publicación no debe ser mayor a 500 caracteres',
            'image_file.mimes' => 'El archivo debe ser de tipo jpeg, png, jpg, svg o webp',
            'image_file.max' => 'El archivo no debe ser mayor a 10MB',
        ];
    }
}

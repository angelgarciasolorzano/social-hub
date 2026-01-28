<?php

namespace App\User\Requests;

use App\User\Enums\UserImageType;
use Illuminate\Foundation\Http\FormRequest;

class UserImageUpdateRequest extends FormRequest
{
    protected UserImageType $field;

    protected function prepareForValidation(): void
    {
        $this->field = $this->route('type');
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            $this->field->value() => ['required', 'mimes:png,jpg,jpeg,webp', 'max:2048']
        ];
    }

    public function messages(): array
    {
        return [
            "{$this->field->value()}.required" => 'La imagen es obligatoria',
            "{$this->field->value()}.max" => 'La imagen no debe ser mayor a 2MB',
            "{$this->field->value()}.mimes" => 'El archivo debe ser de tipo png, jpg, jpeg o webp',
        ];
    }

    public function attributes(): array
    {
        return [
            UserImageType::PROFILE_PICTURE->value() => 'imagen de perfil',
            UserImageType::COVER_IMAGE->value() => 'imagen de portada',
        ];
    }
}
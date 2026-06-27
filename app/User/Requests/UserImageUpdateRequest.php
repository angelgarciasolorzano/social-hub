<?php

declare(strict_types=1);

namespace App\User\Requests;

use App\User\Enums\UserImageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class UserImageUpdateRequest extends FormRequest
{
    protected UserImageType $field;

    #[Override]
    protected function prepareForValidation(): void
    {
        /** @var UserImageType $type */
        $type = $this->route('type');

        $this->field = $type;
    }

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            $this->field->value() => ['required', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * Get the custom messages for the validator errors.
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            $this->field->value().'.required' => 'La imagen es obligatoria',
            $this->field->value().'.max' => 'La imagen no debe ser mayor a 2MB',
            $this->field->value().'.mimes' => 'El archivo debe ser de tipo png, jpg, jpeg o webp',
        ];
    }

    #[Override]
    public function attributes(): array
    {
        return [
            UserImageType::PROFILE_PICTURE->value() => 'imagen de perfil',
            UserImageType::COVER_IMAGE->value() => 'imagen de portada',
        ];
    }
}

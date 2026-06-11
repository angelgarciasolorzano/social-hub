<?php

namespace App\User\Enums;

enum UserImageType: string
{
    case PROFILE_PICTURE = 'profile_picture';
    case COVER_IMAGE = 'cover_image';

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::PROFILE_PICTURE => 'Foto de perfil',
            self::COVER_IMAGE => 'Foto de portada',
        };
    }

    public function successMessage(): string
    {
        return match ($this) {
            self::PROFILE_PICTURE => 'Foto de perfil actualizada correctamente',
            self::COVER_IMAGE => 'Foto de portada actualizada correctamente',
        };
    }

    public function errorMessage(): string
    {
        return match ($this) {
            self::PROFILE_PICTURE => 'No fue posible actualizar la foto de perfil',
            self::COVER_IMAGE => 'No fue posible actualizar la foto de portada',
        };
    }

    public static function invalidTypeMessage(): string
    {
        return 'Tipo de imagen no válido.';
    }
}

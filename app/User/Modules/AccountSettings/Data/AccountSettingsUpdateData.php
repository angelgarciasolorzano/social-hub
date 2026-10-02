<?php

declare(strict_types=1);

namespace App\User\Modules\AccountSettings\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class AccountSettingsUpdateData extends Data
{
    public function __construct(
        public string $name,
        public string|Optional|null $phone,
        public string $preferredLocale,
        public string|Optional|null $biography,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toUserAttributes(): array
    {
        $attributes = [
            'name' => $this->name,
            'preferred_locale' => $this->preferredLocale,
        ];

        if (! $this->phone instanceof Optional) {
            $attributes['phone'] = $this->phone;
        }

        if (! $this->biography instanceof Optional) {
            $attributes['biography'] = $this->biography;
        }

        return $attributes;
    }
}

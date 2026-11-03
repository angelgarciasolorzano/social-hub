<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A current-password field for sensitive panel actions and its rate-limited check: five wrong attempts
 * per minute per administrator and action, the same limits as the backup download.
 */
trait ConfirmsCurrentPassword
{
    protected const string CURRENT_PASSWORD_FIELD = 'current_password';

    private const int CURRENT_PASSWORD_MAX_ATTEMPTS = 5;

    private const int CURRENT_PASSWORD_DECAY_SECONDS = 60;

    protected static function currentPasswordField(): TextInput
    {
        return TextInput::make(self::CURRENT_PASSWORD_FIELD)
            ->label(TranslatedText::of('admin/roles.actions.current_password'))
            ->password()
            ->autocomplete('current-password')
            ->required();
    }

    /**
     * Reject a wrong password, or any password once the attempts of this action are exhausted, with a
     * validation error on `$errorKey` (the field's state path inside an action modal).
     *
     * @throws ValidationException
     */
    protected static function ensureCurrentPassword(
        User $user,
        string $currentPassword,
        string $throttleKeyPrefix,
        string $errorKey = self::CURRENT_PASSWORD_FIELD,
    ): void {
        $throttleKey = $throttleKeyPrefix.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::CURRENT_PASSWORD_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                $errorKey => TranslatedText::of('admin/roles.actions.too_many_attempts', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        if (! Hash::check($currentPassword, $user->password)) {
            RateLimiter::hit($throttleKey, self::CURRENT_PASSWORD_DECAY_SECONDS);

            throw ValidationException::withMessages([
                $errorKey => TranslatedText::of('admin/roles.actions.password_incorrect'),
            ]);
        }

        RateLimiter::clear($throttleKey);
    }
}

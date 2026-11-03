<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Concerns\ConfirmsCurrentPassword;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

const CONFIRMS_CURRENT_PASSWORD_THROTTLE_PREFIX = 'admin-test:current-password:';

/**
 * A class that exposes the concern's protected methods to the tests.
 */
final class ConfirmsCurrentPasswordProbe
{
    use ConfirmsCurrentPassword;

    public static function ensure(User $user, string $currentPassword, string $errorKey = 'current_password'): void
    {
        self::ensureCurrentPassword($user, $currentPassword, CONFIRMS_CURRENT_PASSWORD_THROTTLE_PREFIX, $errorKey);
    }

    public static function fieldName(): string
    {
        return self::currentPasswordField()->getName();
    }
}

/**
 * The validation errors of a password check, or an empty array when it passes.
 *
 * @return array<mixed>
 */
function currentPasswordErrors(User $user, string $currentPassword, string $errorKey = 'current_password'): array
{
    try {
        ConfirmsCurrentPasswordProbe::ensure($user, $currentPassword, $errorKey);
    } catch (ValidationException $validationException) {
        return $validationException->errors();
    }

    return [];
}

it('accepts the current password and clears earlier failed attempts', function (): void {
    $user = createUser();
    currentPasswordErrors($user, 'wrong-password');

    expect(currentPasswordErrors($user, 'password'))
        ->toBeEmpty()
        ->and(RateLimiter::attempts(CONFIRMS_CURRENT_PASSWORD_THROTTLE_PREFIX.$user->id))->toBe(0);
});

it('rejects a wrong password on the given error key', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);
    $user = createUser();

    expect(currentPasswordErrors($user, 'wrong-password', 'mountedActions.0.data.current_password'))
        ->toBe(['mountedActions.0.data.current_password' => [translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.actions.password_incorrect')]]);
});

it('locks the check after five wrong attempts, even for the right password', function (): void {
    $user = createUser();

    foreach (range(1, 5) as $attempt) {
        currentPasswordErrors($user, "wrong-password-{$attempt}");
    }

    expect(currentPasswordErrors($user, 'password'))->toBe([
        'current_password' => [TranslatedText::of('admin/roles.actions.too_many_attempts', [
            'seconds' => RateLimiter::availableIn(CONFIRMS_CURRENT_PASSWORD_THROTTLE_PREFIX.$user->id),
        ])],
    ]);
});

it('builds the current password field', function (): void {
    expect(ConfirmsCurrentPasswordProbe::fieldName())->toBe('current_password');
});

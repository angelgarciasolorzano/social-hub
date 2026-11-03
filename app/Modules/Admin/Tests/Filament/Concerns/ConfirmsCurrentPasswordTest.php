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
 * The validation errors of a password check, or an empty array when it passes. The concern is used by
 * an anonymous class: a named class in a test file would break Composer's PSR-4 autoload map.
 *
 * @return array<mixed>
 */
function currentPasswordErrors(User $user, string $currentPassword, string $errorKey = 'current_password'): array
{
    $probe = new class
    {
        use ConfirmsCurrentPassword;

        public function ensure(User $user, string $currentPassword, string $errorKey): void
        {
            self::ensureCurrentPassword($user, $currentPassword, CONFIRMS_CURRENT_PASSWORD_THROTTLE_PREFIX, $errorKey);
        }
    };

    try {
        $probe->ensure($user, $currentPassword, $errorKey);
    } catch (ValidationException $validationException) {
        return $validationException->errors();
    }

    return [];
}

function currentPasswordFieldName(): string
{
    $probe = new class
    {
        use ConfirmsCurrentPassword;

        public function fieldName(): string
        {
            return self::currentPasswordField()->getName();
        }
    };

    return $probe->fieldName();
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
    expect(currentPasswordFieldName())->toBe('current_password');
});

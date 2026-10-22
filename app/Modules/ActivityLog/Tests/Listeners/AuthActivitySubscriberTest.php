<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;

/**
 * The only activity recorded in the `auth` log for the given event.
 */
function soleAuthActivity(string $eventName): Activity
{
    return Activity::query()
        ->where('log_name', 'auth')
        ->where('event', $eventName)
        ->sole();
}

/**
 * Assert the activity was performed by the user on their own account.
 */
function expectActivityOnOwnAccount(Activity $activity, User $user): void
{
    expect($activity)
        ->subject_type->toBe('user')
        ->subject_id->toBe($user->id)
        ->causer_type->toBe('user')
        ->causer_id->toBe($user->id);
}

it('records a successful web login', function (): void {
    $user = createUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $activity = soleAuthActivity('auth.login');

    expectActivityOnOwnAccount($activity, $user);
    expect($activity->properties?->get('guard'))->toBe('web');
});

it('records the guard of an admin panel login', function (): void {
    $user = createUser();

    Auth::guard('admin')->login($user);

    expect(soleAuthActivity('auth.login')->properties?->get('guard'))->toBe('admin');
});

it('records a logout', function (): void {
    $user = createUser();

    $this->actingAs($user)->post(route('logout'));

    expectActivityOnOwnAccount(soleAuthActivity('auth.logout'), $user);
});

it('records a failed login against an existing account without a causer or credentials', function (): void {
    $user = createUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    $activity = soleAuthActivity('auth.login_failed');

    expect($activity)
        ->subject_type->toBe('user')
        ->subject_id->toBe($user->id)
        ->causer_id->toBeNull()
        ->and($activity->properties?->keys()->all())->toEqualCanonicalizing(['guard', 'ip', 'user_agent']);
});

it('records a failed login for an unknown email without a subject', function (): void {
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

    $activity = soleAuthActivity('auth.login_failed');

    expect($activity)
        ->subject_id->toBeNull()
        ->causer_id->toBeNull()
        ->and($activity->properties?->toJson())->not->toContain('nobody@example.com');
});

it('records a password update', function (): void {
    $user = createUser();

    $this->actingAs($user)->put(route('setting.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    expectActivityOnOwnAccount(soleAuthActivity('auth.password_updated'), $user);
});

it('does not record a password update rejected by validation', function (): void {
    $user = createUser();

    $this->actingAs($user)->put(route('setting.password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    expect(Activity::query()->where('event', 'auth.password_updated')->exists())->toBeFalse();
});

it('records a password reset', function (): void {
    $user = createUser();
    $token = Password::createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-reset-password',
        'password_confirmation' => 'new-reset-password',
    ]);

    expectActivityOnOwnAccount(soleAuthActivity('auth.password_reset'), $user);
});

it('keeps the existing login listeners working', function (): void {
    $user = createUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->refresh()->last_login_at)->not->toBeNull();
});

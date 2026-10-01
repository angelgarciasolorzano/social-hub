<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the registration page component', function (): void {
    $testResponse = $this->get(route('register'));

    $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
        ->component('auth/register/Register'));
});

it('registers and authenticates a new user with a hashed password', function (): void {
    $name = 'New User';
    $email = fake()->unique()->safeEmail();
    $password = 'password';

    Event::fake([Registered::class]);

    $testResponse = $this->post(route('register.store'), [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $testResponse->assertRedirect(route('home', absolute: false));

    $user = User::query()->where('email', $email)->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', [
        'name' => $name,
        'email' => $email,
    ]);

    expect(Hash::check($password, $user->password))->toBeTrue();

    Event::assertDispatched(Registered::class);
});

it('rejects invalid registration input without creating or authenticating a user', function (
    array $payload,
    array $errorFields,
    bool $emailAlreadyExists,
): void {
    if ($emailAlreadyExists) {
        $existingUser = createUser();
        $payload['email'] = $existingUser->email;
    }

    $userCount = User::query()->count('id');

    Event::fake([Registered::class]);

    $testResponse = $this->post(route('register.store'), $payload);

    $testResponse->assertSessionHasErrors($errorFields);
    $this->assertGuest();

    expect(User::query()->count('id'))->toBe($userCount);

    Event::assertNotDispatched(Registered::class);
})->with([
    'empty payload' => [[], ['name', 'email', 'password'], false],
    'invalid email address' => [[
        'name' => 'New User',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], ['email'], false],
    'email already registered' => [[
        'name' => 'New User',
        'email' => 'existing-user@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], ['email'], true],
    'password confirmation does not match' => [[
        'name' => 'New User',
        'email' => 'new-user@example.test',
        'password' => 'password',
        'password_confirmation' => 'different-password',
    ], ['password'], false],
]);

it('keeps authenticated users from creating another account', function (): void {
    $authenticatedUser = createUser();
    $userCount = User::query()->count('id');

    $testResponse = $this->actingAs($authenticatedUser)->post(route('register.store'), [
        'name' => 'Another User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $testResponse->assertRedirect(route('home', absolute: false));
    $this->assertAuthenticatedAs($authenticatedUser);

    expect(User::query()->count('id'))->toBe($userCount);
});

<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('renders the password reset link screen', function (): void {
    $testResponse = $this->get(route('password.request'));

    $testResponse->assertOk();
});

it('sends a reset notification when the email belongs to a user', function (): void {
    Notification::fake();

    $user = createUser();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the password reset screen from the sent notification token', function (): void {
    Notification::fake();

    $user = createUser();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $resetPassword): true {
        $testResponse = $this->get(route('password.reset', $resetPassword->token));

        $testResponse->assertOk();

        return true;
    });
});

it('resets the password with a valid token', function (): void {
    Notification::fake();

    $user = createUser();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $resetPassword) use ($user): true {
        $testResponse = $this->post(route('password.store'), [
            'token' => $resetPassword->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

it('rejects an invalid password reset token', function (): void {
    $user = createUser();

    $testResponse = $this->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $testResponse->assertSessionHasErrors('email');
});

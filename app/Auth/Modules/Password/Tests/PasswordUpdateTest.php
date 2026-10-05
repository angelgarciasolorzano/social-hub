<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;

it('does not expose a standalone password settings page', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->get('/setting/password')
        ->assertMethodNotAllowed();
});

it('updates the password when the current password is correct', function (): void {
    $user = createUser();

    $testResponse = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('setting.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $testResponse
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))
        ->toBeTrue();
});

it('rejects a password update when the current password is incorrect', function (): void {
    $user = createUser();

    $testResponse = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('setting.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $testResponse
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('profile.edit'));
});

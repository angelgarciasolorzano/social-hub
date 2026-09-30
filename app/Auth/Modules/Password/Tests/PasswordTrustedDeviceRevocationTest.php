<?php

declare(strict_types=1);

it('soft deletes trusted devices after a valid password change', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $testResponse = $this->actingAs($user)
        ->from(route('setting.password.edit'))
        ->put(route('setting.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $testResponse
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('setting.password.edit'));

    $this->assertSoftDeleted($trustedDevice);

    expect($user->trustedDevices()->exists())->toBeFalse();
});

it('keeps trusted devices active when the current password is incorrect', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $testResponse = $this->actingAs($user)
        ->from(route('setting.password.edit'))
        ->put(route('setting.password.update'), [
            'current_password' => 'incorrect-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $testResponse
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('setting.password.edit'));

    $this->assertNotSoftDeleted($trustedDevice);

    expect($user->trustedDevices()->exists())->toBeTrue();
});

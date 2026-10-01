<?php

declare(strict_types=1);

it("revokes the authenticated user's trusted devices without affecting other users", function (): void {
    $user = createUser();
    $firstTrustedDevice = createTrustedDevice($user);
    $secondTrustedDevice = createTrustedDevice($user);

    $otherUser = createUser();
    $otherUsersTrustedDevice = createTrustedDevice($otherUser);

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

    $this->assertSoftDeleted($firstTrustedDevice);
    $this->assertSoftDeleted($secondTrustedDevice);

    expect($user->trustedDevices()->exists())->toBeFalse()
        ->and($otherUser->trustedDevices()->whereKey($otherUsersTrustedDevice->getKey())
            ->exists())
        ->toBeTrue();
});

it('keeps every trusted device active when the current password is incorrect', function (): void {
    $user = createUser();
    $firstTrustedDevice = createTrustedDevice($user);
    $secondTrustedDevice = createTrustedDevice($user);

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

    $this->assertNotSoftDeleted($firstTrustedDevice);
    $this->assertNotSoftDeleted($secondTrustedDevice);

    expect($user->trustedDevices()->count())->toBe(2);
});

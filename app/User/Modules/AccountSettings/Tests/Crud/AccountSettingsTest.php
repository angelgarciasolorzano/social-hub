<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('renders the authenticated user account settings', function (): void {
    $user = createUser();
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user->forceFill([
        'phone' => '+505 2222 3333',
        'preferred_locale' => 'es',
        'biography' => 'A short biography.',
    ])->save();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/accountSettings/AccountSettings')
            ->where('accountSettings.id', $user->id)
            ->where('accountSettings.email', $user->email)
            ->where('accountSettings.phone', '+505 2222 3333')
            ->where('accountSettings.preferredLocale', 'es')
            ->where('accountSettings.biography', 'A short biography.')
        );
});

it('updates account settings without allowing other accounts or protected fields to be changed', function (): void {
    $user = createUser();
    $lastLoginAt = now()->subDays(3);

    $user->forceFill([
        'email' => 'original@example.com',
        'last_login_at' => $lastLoginAt,
    ])->save();

    $anotherUser = createUser();
    $anotherUserName = $anotherUser->name;

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Updated Account Owner',
            'phone' => '+505 8888 9999',
            'preferredLocale' => 'es',
            'biography' => 'Updated account biography.',
            'email' => 'changed@example.com',
            'last_login_at' => now()->toIso8601String(),
            'user_id' => $anotherUser->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'))
        ->assertInertiaFlash('type', 'success');

    $user->refresh();
    $anotherUser->refresh();

    expect($user->name)->toBe('Updated Account Owner')
        ->and($user->phone)->toBe('+505 8888 9999')
        ->and($user->preferred_locale)->toBe('es')
        ->and($user->biography)->toBe('Updated account biography.')
        ->and($user->email)->toBe('original@example.com')
        ->and($user->last_login_at?->toIso8601String())->toBe($lastLoginAt->toIso8601String())
        ->and($anotherUser->name)->toBe($anotherUserName);
});

it('preserves omitted optional fields and clears fields sent as null', function (): void {
    $user = createUser();
    $user->forceFill([
        'phone' => '+505 2222 3333',
        'biography' => 'Keep this biography until explicitly cleared.',
    ])->save();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'preferredLocale' => $user->preferred_locale,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->phone)->toBe('+505 2222 3333')
        ->and($user->biography)->toBe('Keep this biography until explicitly cleared.');

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'phone' => null,
            'preferredLocale' => $user->preferred_locale,
            'biography' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->phone)->toBeNull()
        ->and($user->biography)->toBeNull();
});

it('rejects unsupported preferred locales without changing the account', function (): void {
    $user = createUser();
    $originalName = $user->name;

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Should Not Be Saved',
            'preferredLocale' => 'fr',
        ])
        ->assertSessionHasErrors('preferredLocale')
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe($originalName)
        ->and($user->preferred_locale)->toBe('en');
});

it('rejects biographies longer than 160 characters without changing the account', function (): void {
    $user = createUser();
    $originalName = $user->name;

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Should Not Be Saved',
            'preferredLocale' => 'en',
            'biography' => str_repeat('a', 161),
        ])
        ->assertSessionHasErrors('biography')
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe($originalName)
        ->and($user->biography)->toBeNull();
});

it('deletes the account after the current password is confirmed', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->delete(route('user.destroy', $user), [
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

it('keeps the account and session when the current password is incorrect', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('user.destroy', $user), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh())->not->toBeNull();
});

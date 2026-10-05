<?php

declare(strict_types=1);

use App\User\Resources\UserResource;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the authenticated account timestamps without exposing another user', function (): void {
    $user = createUser();
    $createdAt = now()->subDays(10);
    $lastLoginAt = now()->subHours(2);

    $user->forceFill([
        'created_at' => $createdAt,
        'email' => 'account-owner@example.test',
        'phone' => '+505 5555 1111',
        'preferred_locale' => 'es',
        'biography' => 'Account owner biography.',
        'last_login_at' => $lastLoginAt,
    ])->save();
    $user->refresh();

    $otherUser = createUser();
    $otherUser->forceFill([
        'email' => 'other-account-private@example.test',
        'phone' => '+505 5555 2222',
        'preferred_locale' => 'en',
        'biography' => 'Other account private biography.',
        'last_login_at' => now()->subDays(3),
    ])->save();

    config(['inertia.testing.ensure_pages_exist' => false]);

    $testResponse = $this->withoutVite()
        ->actingAs($user)
        ->get(route('profile.edit'));

    $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
        ->component('setting/modules/accountSettings/AccountSettings')
        ->where('accountSettings.id', $user->id)
        ->where('accountSettings.email', 'account-owner@example.test')
        ->where('accountSettings.phone', '+505 5555 1111')
        ->where('accountSettings.preferredLocale', 'es')
        ->where('accountSettings.biography', 'Account owner biography.')
        ->where('accountSettings.createdAt', $user->created_at?->toIso8601String())
        ->where('accountSettings.lastLoginAt', $user->last_login_at?->toIso8601String())
    );

    $testResponse->assertDontSee('other-account-private@example.test');
    $testResponse->assertDontSee('+505 5555 2222');
    $testResponse->assertDontSee('Other account private biography.');
});

it('keeps private account fields out of the public user resource', function (): void {
    $user = createUser();
    $user->forceFill([
        'phone' => '+505 5555 3333',
        'preferred_locale' => 'es',
        'biography' => 'Private account biography.',
        'last_login_at' => now()->subHours(1),
    ])->save();

    $publicUserResource = new UserResource($user)->resolve(request());

    expect($publicUserResource)
        ->not->toHaveKey('phone')
        ->not->toHaveKey('preferredLocale')
        ->not->toHaveKey('biography')
        ->not->toHaveKey('lastLoginAt');
});

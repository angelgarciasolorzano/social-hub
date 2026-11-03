<?php

declare(strict_types=1);

use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * @return array{socket_id: string, channel_name: string}
 */
function userNotificationChannelAuthorizationPayload(User $user): array
{
    return [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-'.$user->receivesBroadcastNotificationsOn(),
    ];
}

beforeEach(function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'social-hub-test-key',
        'broadcasting.connections.reverb.secret' => 'social-hub-test-secret',
        'broadcasting.connections.reverb.app_id' => 'social-hub-test',
    ]);

    Broadcast::forgetDrivers();

    require base_path('routes/channels.php');
});

it('authorizes an active user for their own notification channel', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', userNotificationChannelAuthorizationPayload($user))
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('returns 403 when an authenticated user requests another users notification channel', function (): void {
    $user = createUser();
    $authenticatedUser = createUser();

    $this->actingAs($authenticatedUser)
        ->postJson('/broadcasting/auth', userNotificationChannelAuthorizationPayload($user))
        ->assertForbidden();
});

it('returns 403 when a guest requests a user notification channel', function (): void {
    $user = createUser();

    $this->postJson('/broadcasting/auth', userNotificationChannelAuthorizationPayload($user))
        ->assertForbidden();
});

it('returns 403 and clears the session when a suspended user requests their notification channel', function (): void {
    $user = createSuspendedUser();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', userNotificationChannelAuthorizationPayload($user))
        ->assertForbidden();

    $this->assertGuest();
});

it('returns the persisted preferred locale for notifications', function (PreferredLocale $preferredLocale): void {
    $user = createUser();
    $user->forceFill(['preferred_locale' => $preferredLocale])->save();

    expect($user->refresh()->preferredLocale())->toBe($preferredLocale->value);
})->with([
    'English' => [PreferredLocale::ENGLISH],
    'Spanish' => [PreferredLocale::SPANISH],
]);

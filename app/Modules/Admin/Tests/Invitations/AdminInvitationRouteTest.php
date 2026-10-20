<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\AdminInvitation;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @return array{invitation: AdminInvitation, token: string} */
function createAdminInvitationForRoute(?User $targetUser = null): array
{
    $plainToken = bin2hex(random_bytes(32));

    /** @var Factory<AdminInvitation> $factory */
    $factory = AdminInvitation::factory();

    if ($targetUser instanceof User) {
        $factory = $factory->for($targetUser, 'targetUser');
    }

    $adminInvitation = $factory->createOne([
        'email' => $targetUser instanceof User ? $targetUser->email : 'new-admin@example.com',
        'token_hash' => hash('sha256', $plainToken),
    ]);

    return [
        'invitation' => $adminInvitation,
        'token' => $plainToken,
    ];
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('serves a valid invitation to a guest without changing the account', function (): void {
    $targetUser = createUser();
    $originalName = $targetUser->name;
    $originalPassword = $targetUser->password;
    $invitationData = createAdminInvitationForRoute($targetUser);

    $this->assertGuest('admin');

    $this->get(route('admin.invitations.accept', ['token' => $invitationData['token']]))
        ->assertOk()
        ->assertSee($targetUser->email);

    $this->assertGuest('admin');

    expect($targetUser->refresh()->name)
        ->toBe($originalName)
        ->and($targetUser->password)
        ->toBe($originalPassword)
        ->and($targetUser->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeFalse();
});

it('shows an unavailable page for an unknown token', function (): void {
    $originalUserCount = User::query()->count('*');
    $unknownToken = bin2hex(random_bytes(32));

    $this->get(route('admin.invitations.accept', ['token' => $unknownToken]))
        ->assertOk()
        ->assertSee('Invitation unavailable');

    expect(User::query()->count('*'))->toBe($originalUserCount);
});

it('shows expired and already accepted invitations as unavailable without mutating the target account', function (string $invitationState): void {
    $targetUser = createUser();
    $targetUser->forceFill(['email_verified_at' => null])->save();
    $originalName = $targetUser->name;
    $originalPassword = $targetUser->password;
    $invitationData = createAdminInvitationForRoute($targetUser);
    $acceptedAt = null;

    if ($invitationState === 'expired') {
        $invitationData['invitation']->forceFill([
            'expires_at' => CarbonImmutable::now()->subMinute(),
        ])->save();
    } elseif ($invitationState === 'accepted') {
        $acceptedAt = CarbonImmutable::now()->startOfSecond();
        $invitationData['invitation']->forceFill(['accepted_at' => $acceptedAt])->save();
    } else {
        throw new LogicException('The invitation state must be expired or accepted.');
    }

    $this->get(route('admin.invitations.accept', ['token' => $invitationData['token']]))
        ->assertOk()
        ->assertSee('Invitation unavailable')
        ->assertDontSee('wire:model="name"');

    $targetUser->refresh();

    expect($targetUser->name)
        ->toBe($originalName)
        ->and($targetUser->password)
        ->toBe($originalPassword)
        ->and($targetUser->hasVerifiedEmail())
        ->toBeFalse()
        ->and($targetUser->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeFalse();

    if ($acceptedAt instanceof CarbonImmutable) {
        expect($invitationData['invitation']->refresh()->accepted_at)
            ->toEqual($acceptedAt);
    }
})->with([
    'expired invitation' => ['expired'],
    'already accepted invitation' => ['accepted'],
]);

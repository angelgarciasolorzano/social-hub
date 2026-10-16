<?php

declare(strict_types=1);

use App\Modules\Admin\Models\AdminInvitation;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create a persisted invitation and optionally associate an existing target user.
 *
 * @param  array<string, mixed>  $attributes
 */
function createAdminInvitation(?User $targetUser = null, array $attributes = []): AdminInvitation
{
    /** @var Factory<AdminInvitation> $factory */
    $factory = AdminInvitation::factory();

    if ($targetUser instanceof User) {
        $factory = $factory->for($targetUser, 'targetUser');
    }

    return $factory->createOne($attributes);
}

it('normalizes the email before persisting an invitation', function (): void {
    $adminInvitation = createAdminInvitation(attributes: [
        'email' => '  Admin+Test@Example.COM  ',
    ]);

    expect($adminInvitation->refresh()->email)->toBe('admin+test@example.com');
});

it('casts expiration and acceptance timestamps as immutable dates', function (): void {
    $expiresAt = CarbonImmutable::parse('2026-11-01 12:00:00 UTC');
    $acceptedAt = CarbonImmutable::parse('2026-10-16 12:00:00 UTC');

    $adminInvitation = createAdminInvitation(attributes: [
        'expires_at' => $expiresAt,
        'accepted_at' => $acceptedAt,
    ]);

    expect($adminInvitation->refresh()->getAttribute('expires_at'))
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and($adminInvitation->getAttribute('accepted_at'))
        ->toBeInstanceOf(CarbonImmutable::class);
});

it('relates an invitation to the user who sent it', function (): void {
    $adminInvitation = createAdminInvitation();

    expect($adminInvitation->inviter->getKey())->toBe($adminInvitation->invited_by_user_id);
});

it('relates an invitation to an existing target user', function (): void {
    $targetUser = createUser();

    $adminInvitation = createAdminInvitation($targetUser, [
        'email' => $targetUser->email,
    ]);

    expect($adminInvitation->targetUser()->firstOrFail()->getKey())->toBe($targetUser->getKey());
});

it('allows invitations to a new email without a target user', function (): void {
    $adminInvitation = createAdminInvitation(attributes: [
        'email' => 'new-admin@example.com',
    ]);

    expect($adminInvitation->email)
        ->toBe('new-admin@example.com')
        ->and($adminInvitation->target_user_id)
        ->toBeNull()
        ->and($adminInvitation->targetUser)
        ->toBeNull()
        ->and($adminInvitation->accepted_at)
        ->toBeNull();
});
